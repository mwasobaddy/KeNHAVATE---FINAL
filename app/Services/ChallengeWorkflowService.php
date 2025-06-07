<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\ChallengeReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * KeNHAVATE Challenge Workflow Service
 * Handles challenge submission and review workflow with gamification integration
 */
class ChallengeWorkflowService
{
    protected array $statusTransitions = [
        'draft' => [
            'submitted' => ['user'],
        ],
        'submitted' => [
            'under_review' => ['manager', 'administrator', 'challenge_reviewer'],
            'rejected' => ['manager', 'administrator'],
        ],
        'under_review' => [
            'manager_review' => ['manager', 'administrator'],
            'sme_review' => ['manager', 'administrator'],
            'rejected' => ['manager', 'administrator', 'challenge_reviewer'],
            'approved' => ['manager', 'administrator', 'challenge_reviewer'],
        ],
        'manager_review' => [
            'sme_review' => ['manager', 'administrator'],
            'approved' => ['manager', 'administrator'],
            'rejected' => ['manager', 'administrator'],
            'needs_revision' => ['manager', 'administrator'],
        ],
        'sme_review' => [
            'approved' => ['sme', 'administrator'],
            'rejected' => ['sme', 'administrator'],
            'needs_revision' => ['sme', 'administrator'],
        ],
        'approved' => [
            'winner' => ['manager', 'administrator', 'board_member'],
            'archived' => ['administrator'],
        ],
        'rejected' => [
            'archived' => ['administrator'],
        ],
        'needs_revision' => [
            'submitted' => ['user'], // Resubmission
            'archived' => ['administrator'],
        ],
        'winner' => [
            'archived' => ['administrator'],
        ],
    ];

    public function __construct(
        private GamificationService $gamificationService,
        private ReviewTrackingService $reviewTrackingService,
        private AuditService $auditService
    ) {}

    /**
     * Submit a challenge solution
     */
    public function submitSolution(ChallengeSubmission $submission, User $user): bool
    {
        // Validate that the submission is in draft status
        if ($submission->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft submissions can be submitted'
            ]);
        }

        // Validate that the user is the author
        if ($user->id !== $submission->participant_id && !$user->hasRole('administrator')) {
            throw ValidationException::withMessages([
                'authorization' => 'You can only submit your own solutions'
            ]);
        }

        // Validate required fields
        if (empty($submission->title) || empty($submission->description)) {
            throw ValidationException::withMessages([
                'validation' => 'Submission must have both title and description'
            ]);
        }

        // Check challenge is still accepting submissions
        $challenge = $submission->challenge;
        if ($challenge->status !== 'active' || ($challenge->deadline && now()->isAfter($challenge->deadline))) {
            throw ValidationException::withMessages([
                'deadline' => 'This challenge is no longer accepting submissions'
            ]);
        }

        return DB::transaction(function () use ($submission, $user) {
            // Award gamification points for challenge participation
            $this->gamificationService->awardChallengeParticipation($user, $submission);

            // Check for weekend warrior bonus
            $this->gamificationService->checkWeekendBonus($user, $submission);

            // Transition to submitted status
            $this->transitionStatus($submission, 'submitted', $user, 'Solution submitted for review');

            return true;
        });
    }

    /**
     * Submit a review for a challenge submission
     */
    public function submitReview(
        ChallengeSubmission $submission,
        User $reviewer,
        array $reviewData
    ): ChallengeReview {
        // Validate reviewer can review this submission
        if (!$this->canUserReview($reviewer, $submission)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to review this submission'
            ]);
        }

        return DB::transaction(function () use ($submission, $reviewer, $reviewData) {
            // Create or update review
            $review = ChallengeReview::updateOrCreate(
                [
                    'challenge_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ],
                array_merge($reviewData, [
                    'reviewed_at' => now(),
                ])
            );

            // Process review for gamification and bonuses
            $this->reviewTrackingService->processChallengeReview($review);

            // Update submission status based on review
            $this->updateSubmissionAfterReview($submission, $review);

            // Log the review
            $this->auditService->log(
                'challenge_review_submitted',
                'ChallengeSubmission',
                $submission->id,
                null,
                [
                    'reviewer_id' => $reviewer->id,
                    'score' => $review->score,
                    'recommendation' => $review->recommendation,
                ]
            );

            return $review;
        });
    }

    /**
     * Transition submission status
     */
    public function transitionStatus(
        ChallengeSubmission $submission,
        string $newStatus,
        User $user,
        ?string $comments = null
    ): bool {
        $currentStatus = $submission->status;

        // Validate transition is allowed
        if (!$this->canTransitionStatus($submission, $newStatus, $user)) {
            throw ValidationException::withMessages([
                'transition' => "Cannot transition from {$currentStatus} to {$newStatus}"
            ]);
        }

        return DB::transaction(function () use ($submission, $newStatus, $user, $comments, $currentStatus) {
            // Update submission status
            $submission->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

            // Create audit log
            $this->auditService->log(
                'status_change',
                'ChallengeSubmission',
                $submission->id,
                ['status' => $currentStatus],
                [
                    'status' => $newStatus,
                    'changed_by' => $user->id,
                    'comments' => $comments,
                ]
            );

            // Handle special status transitions
            $this->handleSpecialStatusTransitions($submission, $newStatus, $user);

            return true;
        });
    }

    /**
     * Mark submission as winner
     */
    public function markAsWinner(
        ChallengeSubmission $submission,
        User $user,
        int $ranking = 1,
        ?float $prizeAmount = null
    ): bool {
        // Validate user can mark winners
        if (!$user->hasAnyRole(['manager', 'administrator', 'board_member'])) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to mark winners'
            ]);
        }

        // Validate submission is approved
        if ($submission->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Only approved submissions can be marked as winners'
            ]);
        }

        return DB::transaction(function () use ($submission, $user, $ranking, $prizeAmount) {
            // Update submission
            $submission->update([
                'status' => 'winner',
                'ranking' => $ranking,
                'prize_amount' => $prizeAmount,
                'winner_announced_at' => now(),
            ]);

            // Award winner points
            $this->gamificationService->awardChallengeWinner(
                $submission->participant,
                $submission,
                $ranking
            );

            // Log winner announcement
            $this->auditService->log(
                'winner_announced',
                'ChallengeSubmission',
                $submission->id,
                ['status' => 'approved'],
                [
                    'status' => 'winner',
                    'ranking' => $ranking,
                    'announced_by' => $user->id,
                    'prize_amount' => $prizeAmount,
                ]
            );

            return true;
        });
    }

    /**
     * Check if user can review submission
     */
    protected function canUserReview(User $user, ChallengeSubmission $submission): bool
    {
        // Cannot review own submissions
        if ($submission->participant_id === $user->id) {
            return false;
        }

        // Cannot review submissions for challenges they created
        if ($submission->challenge->creator_id === $user->id) {
            return false;
        }

        // Check if submission is in reviewable status
        if (!in_array($submission->status, ['submitted', 'under_review', 'manager_review', 'sme_review'])) {
            return false;
        }

        // Role-based review permissions
        return match ($submission->status) {
            'submitted', 'under_review' => $user->hasAnyRole(['manager', 'administrator', 'challenge_reviewer']),
            'manager_review' => $user->hasAnyRole(['manager', 'administrator']),
            'sme_review' => $user->hasAnyRole(['sme', 'administrator']),
            default => false,
        };
    }

    /**
     * Check if status transition is allowed
     */
    protected function canTransitionStatus(ChallengeSubmission $submission, string $newStatus, User $user): bool
    {
        $currentStatus = $submission->status;

        // Check if transition exists
        if (!isset($this->statusTransitions[$currentStatus][$newStatus])) {
            return false;
        }

        // Check user has required role
        $requiredRoles = $this->statusTransitions[$currentStatus][$newStatus];
        foreach ($requiredRoles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update submission status after review
     */
    protected function updateSubmissionAfterReview(ChallengeSubmission $submission, ChallengeReview $review): void
    {
        // Count total reviews and their recommendations
        $allReviews = $submission->challengeReviews;
        $totalReviews = $allReviews->count();
        $approvalCount = $allReviews->where('recommendation', 'approve')->count();
        $rejectionCount = $allReviews->where('recommendation', 'reject')->count();
        $revisionCount = $allReviews->where('recommendation', 'needs_revision')->count();

        // Determine if we have enough reviews for a decision
        $minReviews = 2; // Configurable minimum reviews needed

        if ($totalReviews >= $minReviews) {
            $averageScore = $allReviews->avg('score');

            // Decision logic
            if ($approvalCount > $rejectionCount && $averageScore >= 70) {
                $newStatus = 'approved';
            } elseif ($rejectionCount > $approvalCount || $averageScore < 50) {
                $newStatus = 'rejected';
            } elseif ($revisionCount > 0 && $averageScore >= 50) {
                $newStatus = 'needs_revision';
            } else {
                return; // No clear decision yet
            }

            // Update status
            $submission->update(['status' => $newStatus]);

            // Log status change
            $this->auditService->log(
                'auto_status_change',
                'ChallengeSubmission',
                $submission->id,
                ['status' => $submission->getOriginal('status')],
                [
                    'status' => $newStatus,
                    'trigger' => 'review_completion',
                    'total_reviews' => $totalReviews,
                    'average_score' => $averageScore,
                ]
            );
        }
    }

    /**
     * Handle special status transitions
     */
    protected function handleSpecialStatusTransitions(ChallengeSubmission $submission, string $newStatus, User $user): void
    {
        switch ($newStatus) {
            case 'submitted':
                // Send notification to reviewers
                $this->notifyReviewers($submission);
                break;

            case 'approved':
                // Award bonus points for approved submission
                $this->gamificationService->awardSubmissionApproval($submission->participant, $submission);
                break;

            case 'winner':
                // Already handled in markAsWinner method
                break;
        }
    }

    /**
     * Notify reviewers of new submission
     */
    protected function notifyReviewers(ChallengeSubmission $submission): void
    {
        // Get potential reviewers based on challenge settings
        $reviewers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['manager', 'challenge_reviewer', 'sme']);
        })->get();

        // Send notifications (implement notification service integration)
        foreach ($reviewers as $reviewer) {
            // This would integrate with your notification system
            // NotificationService::send($reviewer, 'new_submission', $submission);
        }
    }

    /**
     * Get pending reviews for a user
     */
    public function getPendingReviews(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $roleStatusMap = [
            'manager' => ['submitted', 'under_review', 'manager_review'],
            'challenge_reviewer' => ['submitted', 'under_review'],
            'sme' => ['sme_review'],
            'administrator' => ['submitted', 'under_review', 'manager_review', 'sme_review'],
        ];

        $statuses = [];
        foreach ($roleStatusMap as $role => $statusList) {
            if ($user->hasRole($role)) {
                $statuses = array_merge($statuses, $statusList);
            }
        }

        if (empty($statuses)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return ChallengeSubmission::whereIn('status', array_unique($statuses))
            ->where('participant_id', '!=', $user->id) // Exclude own submissions
            ->whereDoesntHave('challengeReviews', function ($query) use ($user) {
                $query->where('reviewer_id', $user->id);
            })
            ->with(['challenge', 'participant'])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Get review statistics for a user
     */
    public function getReviewStats(User $user): array
    {
        $reviews = ChallengeReview::where('reviewer_id', $user->id);

        return [
            'total_reviews' => $reviews->count(),
            'reviews_this_month' => $reviews->whereMonth('created_at', now()->month)->count(),
            'reviews_this_week' => $reviews->whereDate('created_at', '>=', now()->startOfWeek())->count(),
            'average_score' => $reviews->avg('score') ?? 0,
            'pending_reviews' => $this->getPendingReviews($user)->count(),
        ];
    }
}
