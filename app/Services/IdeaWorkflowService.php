<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\User;
use App\Models\Review;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdeaWorkflowService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Valid stage transitions and their required roles
     */
    protected array $stageTransitions = [
        'draft' => ['submitted' => ['user']],
        'submitted' => ['manager_review' => ['manager', 'admin']],
        'manager_review' => [
            'sme_review' => ['manager', 'admin'],
            'rejected' => ['manager', 'admin'],
            'needs_revision' => ['manager', 'admin']
        ],
        'sme_review' => [
            'collaboration' => ['sme', 'admin'],
            'board_review' => ['sme', 'admin'],
            'rejected' => ['sme', 'admin'],
            'needs_revision' => ['sme', 'admin']
        ],
        'collaboration' => [
            'board_review' => ['sme', 'admin'],
            'rejected' => ['sme', 'admin']
        ],
        'board_review' => [
            'implementation' => ['board_member', 'admin'],
            'rejected' => ['board_member', 'admin'],
            'needs_revision' => ['board_member', 'admin']
        ],
        'implementation' => [
            'completed' => ['manager', 'admin'],
            'archived' => ['manager', 'admin']
        ],
        'completed' => ['archived' => ['admin']],
        'needs_revision' => ['draft' => ['user'], 'archived' => ['admin']],
        'rejected' => ['archived' => ['admin']]
    ];

    /**
     * Transition an idea to a new stage
     */
    public function transitionStage(
        Idea $idea, 
        string $newStage, 
        User $user, 
        ?string $comments = null,
        ?string $decision = null,
        ?array $additionalData = []
    ): bool {
        return DB::transaction(function () use ($idea, $newStage, $user, $comments, $decision, $additionalData) {
            // Validate the transition
            $this->validateTransition($idea, $newStage, $user);

            $oldStage = $idea->current_stage;

            // Create audit log
            $this->createAuditLog($idea, $oldStage, $newStage, $user, $comments);

            // Update idea stage
            $idea->update([
                'current_stage' => $newStage,
                'last_stage_change' => now(),
                'last_reviewer_id' => $user->id
            ]);

            // Create review record only for review stages
            if ($this->isReviewStage($newStage)) {
                $this->createReviewRecord($idea, $user, $newStage, $decision, $comments, $additionalData);
            }

            // Handle special stage logic
            $this->handleStageSpecificLogic($idea, $newStage, $oldStage, $user);

            // Send notifications
            $this->sendNotifications($idea, $newStage, $oldStage, $user);

            return true;
        });
    }

    /**
     * Validate if a stage transition is allowed
     */
    protected function validateTransition(Idea $idea, string $newStage, User $user): void
    {
        $currentStage = $idea->current_stage;

        // Check if transition is valid
        if (!isset($this->stageTransitions[$currentStage][$newStage])) {
            throw ValidationException::withMessages([
                'stage' => "Invalid stage transition from {$currentStage} to {$newStage}"
            ]);
        }

        // Check if user has required role
        $requiredRoles = $this->stageTransitions[$currentStage][$newStage];
        $hasRole = false;

        foreach ($requiredRoles as $role) {
            if ($user->hasRole($role)) {
                $hasRole = true;
                break;
            }
        }

        if (!$hasRole) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to perform this transition'
            ]);
        }

        // Business rule: Users cannot review their own ideas
        if ($user->id === $idea->author_id && in_array($newStage, ['manager_review', 'sme_review', 'board_review'])) {
            throw ValidationException::withMessages([
                'conflict' => 'You cannot review your own idea'
            ]);
        }

        // Check if idea is locked during review
        if (in_array($currentStage, ['manager_review', 'sme_review', 'board_review', 'collaboration']) && 
            !$user->hasRole(['manager', 'sme', 'board_member', 'admin'])) {
            throw ValidationException::withMessages([
                'locked' => 'Idea is currently under review and cannot be modified'
            ]);
        }
    }

    /**
     * Create audit log entry for stage transition
     */
    protected function createAuditLog(
        Idea $idea, 
        string $oldStage, 
        string $newStage, 
        User $user, 
        ?string $comments = null
    ): void {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'status_change',
            'entity_type' => 'Idea',
            'entity_id' => $idea->id,
            'old_values' => ['current_stage' => $oldStage],
            'new_values' => ['current_stage' => $newStage],
            'metadata' => [
                'comments' => $comments,
                'transition' => "{$oldStage} → {$newStage}"
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Create review record for review stages
     */
    protected function createReviewRecord(
        Idea $idea, 
        User $user, 
        string $stage, 
        ?string $decision = null,
        ?string $comments = null,
        array $additionalData = []
    ): Review {
        return Review::create([
            'reviewable_type' => Idea::class,
            'reviewable_id' => $idea->id,
            'reviewer_id' => $user->id,
            'review_stage' => $stage,
            'decision' => $decision ?? 'pending',
            'comments' => $comments,
            'overall_score' => $additionalData['overall_score'] ?? null,
            'criteria_scores' => $additionalData['criteria_scores'] ?? null,
            'feedback' => $additionalData['feedback'] ?? null,
            'completed_at' => $decision ? now() : null,
        ]);
    }

    /**
     * Handle stage-specific business logic
     */
    protected function handleStageSpecificLogic(Idea $idea, string $newStage, string $oldStage, User $user): void
    {
        switch ($newStage) {
            case 'submitted':
                // Lock the idea for editing
                $idea->update(['submitted_at' => now()]);
                break;
                
            case 'collaboration':
                // Enable collaboration features
                $idea->update(['collaboration_enabled' => true]);
                break;
                
            case 'implementation':
                // Set implementation start date
                $idea->update(['implementation_started_at' => now()]);
                break;
                
            case 'completed':
                // Set completion date and disable collaboration
                $idea->update([
                    'completed_at' => now(),
                    'collaboration_enabled' => false
                ]);
                break;
                
            case 'draft':
                // Reset submission data when returning to draft
                $idea->update([
                    'submitted_at' => null,
                    'collaboration_enabled' => false
                ]);
                break;
        }
    }

    /**
     * Send notifications for stage transitions
     */
    protected function sendNotifications(Idea $idea, string $newStage, string $oldStage, User $actor): void
    {
        // Notify idea author of status changes
        if ($actor->id !== $idea->author_id) {
            $this->notificationService->sendNotification(
                $idea->author,
                'status_change',
                [
                    'title' => 'Idea Status Updated',
                    'message' => "Your idea '{$idea->title}' has moved from {$oldStage} to {$newStage}",
                    'related_type' => 'Idea',
                    'related_id' => $idea->id,
                    'actor' => $actor->name
                ]
            );
        }

        // Notify reviewers when assigned
        $this->notifyReviewers($idea, $newStage);

        // Notify managers of new submissions
        if ($newStage === 'submitted') {
            $managers = User::role('manager')->get();
            foreach ($managers as $manager) {
                $this->notificationService->sendNotification(
                    $manager,
                    'review_assigned',
                    [
                        'title' => 'New Idea for Review',
                        'message' => "A new idea '{$idea->title}' has been submitted for manager review",
                        'related_type' => 'Idea',
                        'related_id' => $idea->id,
                        'author' => $idea->author->name
                    ]
                );
            }
        }
    }

    /**
     * Notify appropriate reviewers based on stage
     */
    protected function notifyReviewers(Idea $idea, string $stage): void
    {
        $roleMap = [
            'manager_review' => 'manager',
            'sme_review' => 'sme',
            'board_review' => 'board_member'
        ];

        if (!isset($roleMap[$stage])) {
            return;
        }

        $reviewers = User::role($roleMap[$stage])->get();
        
        foreach ($reviewers as $reviewer) {
            // Skip if reviewer is the author
            if ($reviewer->id === $idea->author_id) {
                continue;
            }

            $this->notificationService->sendNotification(
                $reviewer,
                'review_assigned',
                [
                    'title' => 'Review Assignment',
                    'message' => "You have been assigned to review idea '{$idea->title}'",
                    'related_type' => 'Idea',
                    'related_id' => $idea->id,
                    'stage' => $stage,
                    'author' => $idea->author->name
                ]
            );
        }
    }

    /**
     * Check if a stage is a review stage
     */
    protected function isReviewStage(string $stage): bool
    {
        return in_array($stage, ['manager_review', 'sme_review', 'board_review']);
    }

    /**
     * Get next possible stages for an idea
     */
    public function getNextStages(Idea $idea, User $user): array
    {
        $currentStage = $idea->current_stage;
        $possibleStages = [];

        if (!isset($this->stageTransitions[$currentStage])) {
            return [];
        }

        foreach ($this->stageTransitions[$currentStage] as $stage => $requiredRoles) {
            foreach ($requiredRoles as $role) {
                if ($user->hasRole($role)) {
                    $possibleStages[] = $stage;
                    break;
                }
            }
        }

        // Remove stages where user would be reviewing their own idea
        if ($user->id === $idea->author_id) {
            $possibleStages = array_filter($possibleStages, function ($stage) {
                return !in_array($stage, ['manager_review', 'sme_review', 'board_review']);
            });
        }

        return array_unique($possibleStages);
    }

    /**
     * Get pending reviews for a user
     */
    public function getPendingReviews(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $roleStageMap = [
            'manager' => 'manager_review',
            'sme' => 'sme_review', 
            'board_member' => 'board_review'
        ];

        $stages = [];
        foreach ($roleStageMap as $role => $stage) {
            if ($user->hasRole($role)) {
                $stages[] = $stage;
            }
        }

        if (empty($stages)) {
            return collect();
        }

        return Idea::whereIn('current_stage', $stages)
            ->where('author_id', '!=', $user->id) // Exclude own ideas
            ->with(['author', 'category'])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Submit a review for an idea
     */
    public function submitReview(
        Idea $idea,
        User $reviewer,
        string $decision,
        string $comments,
        ?float $overallScore = null,
        ?array $criteriaScores = null,
        ?string $feedback = null
    ): Review {
        // Validate reviewer can review this idea
        $pendingReviews = $this->getPendingReviews($reviewer);
        if (!$pendingReviews->contains('id', $idea->id)) {
            throw ValidationException::withMessages([
                'authorization' => 'You are not authorized to review this idea'
            ]);
        }

        return DB::transaction(function () use ($idea, $reviewer, $decision, $comments, $overallScore, $criteriaScores, $feedback) {
            // Create the review
            $review = $this->createReviewRecord($idea, $reviewer, $idea->current_stage, $decision, $comments, [
                'overall_score' => $overallScore,
                'criteria_scores' => $criteriaScores,
                'feedback' => $feedback
            ]);

            // Transition to next stage based on decision
            if ($decision === 'approved') {
                $nextStage = $this->getApprovedNextStage($idea->current_stage);
                if ($nextStage) {
                    $this->transitionStage($idea, $nextStage, $reviewer, "Review completed: {$comments}", $decision);
                }
            } elseif ($decision === 'rejected') {
                $this->transitionStage($idea, 'rejected', $reviewer, "Review completed: {$comments}", $decision);
            } elseif ($decision === 'needs_revision') {
                $this->transitionStage($idea, 'needs_revision', $reviewer, "Review completed: {$comments}", $decision);
            }

            return $review;
        });
    }

    /**
     * Get the next stage when a review is approved
     */
    protected function getApprovedNextStage(string $currentStage): ?string
    {
        $approvedTransitions = [
            'manager_review' => 'sme_review',
            'sme_review' => 'board_review',
            'board_review' => 'implementation'
        ];

        return $approvedTransitions[$currentStage] ?? null;
    }

    /**
     * Submit an idea from draft to submitted stage
     */
    public function submitIdea(Idea $idea, User $user): bool
    {
        // Validate that the idea is in draft stage
        if ($idea->current_stage !== 'draft') {
            throw ValidationException::withMessages([
                'stage' => 'Only ideas in draft stage can be submitted'
            ]);
        }

        // Validate that the user is the author or has admin permissions
        if ($user->id !== $idea->author_id && !$user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'authorization' => 'You can only submit your own ideas'
            ]);
        }

        // Validate idea has required fields for submission
        if (empty($idea->title) || empty($idea->description)) {
            throw ValidationException::withMessages([
                'validation' => 'Idea must have both title and description to be submitted'
            ]);
        }

        // Transition to submitted stage
        return $this->transitionStage($idea, 'submitted', $user, 'Idea submitted for review');
    }
}
