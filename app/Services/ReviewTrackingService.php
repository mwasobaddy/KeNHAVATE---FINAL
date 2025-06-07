<?php

namespace App\Services;

use App\Models\User;
use App\Models\Review;
use App\Models\ChallengeReview;
use App\Models\Idea;
use App\Models\ChallengeSubmission;
use Carbon\Carbon;

/**
 * KeNHAVATE Review Tracking Service
 * Handles first half reviewer bonuses and early review bonuses
 */
class ReviewTrackingService
{
    private GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Process review completion for ideas
     */
    public function processIdeaReview(Review $review): void
    {
        $user = $review->reviewer;
        $idea = $review->reviewable;

        if (!$idea instanceof Idea) {
            return;
        }

        // Award first half reviewer bonus
        $this->gamificationService->awardFirstHalfReviewerBonus($user, $review);

        // Award early review bonus if applicable
        $submissionTime = $idea->submitted_at ?? $idea->created_at;
        $this->gamificationService->awardEarlyReviewBonus($user, $review, $submissionTime);

        // Check for weekend bonus
        $this->gamificationService->checkWeekendBonus($user, $review);

        // Award base review completion points
        $this->awardBaseReviewPoints($user, $review);
    }

    /**
     * Process review completion for challenges
     */
    public function processChallengeReview(ChallengeReview $review): void
    {
        $user = $review->reviewer;
        $submission = $review->submission;

        // Award first half reviewer bonus
        $this->gamificationService->awardFirstHalfChallengeReviewerBonus($user, $review);

        // Award early review bonus if applicable
        $submissionTime = $submission->submitted_at ?? $submission->created_at;
        $this->gamificationService->awardEarlyReviewBonus($user, $review, $submissionTime);

        // Check for weekend bonus
        $this->gamificationService->checkWeekendBonus($user, $review);

        // Award base review completion points
        $this->awardBaseChallengeReviewPoints($user, $review);
    }

    /**
     * Award base review completion points for ideas
     */
    private function awardBaseReviewPoints(User $user, Review $review): void
    {
        $idea = $review->reviewable;
        
        $points = $this->gamificationService->createUserPoint(
            $user,
            'review_completion',
            25, // Base review points
            "Review completed for idea: {$idea->title}",
            $review
        );

        $this->sendPointNotification($user, $points);
    }

    /**
     * Award base review completion points for challenges
     */
    private function awardBaseChallengeReviewPoints(User $user, ChallengeReview $review): void
    {
        $submission = $review->submission;
        $challenge = $submission->challenge;
        
        $points = $this->gamificationService->createUserPoint(
            $user,
            'review_completion',
            30, // Base challenge review points (slightly higher)
            "Challenge review completed for: {$challenge->title}",
            $review
        );

        $this->sendPointNotification($user, $points);
    }

    /**
     * Get reviewer statistics
     */
    public function getReviewerStats(User $user): array
    {
        $ideaReviews = Review::where('reviewer_id', $user->id)
            ->whereHasMorph('reviewable', [Idea::class])
            ->count();

        $challengeReviews = ChallengeReview::where('reviewer_id', $user->id)
            ->count();

        $totalReviews = $ideaReviews + $challengeReviews;

        // Count fast reviews (within 24 hours)
        $fastIdeaReviews = $this->getFastReviewCount($user, 'ideas');
        $fastChallengeReviews = $this->getFastReviewCount($user, 'challenges');
        $totalFastReviews = $fastIdeaReviews + $fastChallengeReviews;

        // Count first half bonuses
        $firstHalfBonuses = \App\Models\UserPoint::where('user_id', $user->id)
            ->where('action', 'review_completion')
            ->where('description', 'like', '%First half reviewer bonus%')
            ->count();

        return [
            'total_reviews' => $totalReviews,
            'idea_reviews' => $ideaReviews,
            'challenge_reviews' => $challengeReviews,
            'fast_reviews' => $totalFastReviews,
            'first_half_bonuses' => $firstHalfBonuses,
            'review_efficiency' => $totalReviews > 0 ? round(($totalFastReviews / $totalReviews) * 100, 1) : 0,
        ];
    }

    /**
     * Get fast review count for user
     */
    private function getFastReviewCount(User $user, string $type): int
    {
        if ($type === 'ideas') {
            return Review::where('reviewer_id', $user->id)
                ->whereHasMorph('reviewable', [Idea::class], function ($query) {
                    $query->whereRaw('reviews.created_at <= DATE_ADD(ideas.submitted_at, INTERVAL 24 HOUR)')
                          ->orWhereRaw('reviews.created_at <= DATE_ADD(ideas.created_at, INTERVAL 24 HOUR)');
                })
                ->count();
        } else {
            return ChallengeReview::where('reviewer_id', $user->id)
                ->whereHas('submission', function ($query) {
                    $query->whereRaw('challenge_reviews.created_at <= DATE_ADD(challenge_submissions.submitted_at, INTERVAL 24 HOUR)')
                          ->orWhereRaw('challenge_reviews.created_at <= DATE_ADD(challenge_submissions.created_at, INTERVAL 24 HOUR)');
                })
                ->count();
        }
    }

    /**
     * Get top reviewers leaderboard
     */
    public function getTopReviewers(int $limit = 10): array
    {
        $ideaReviewers = Review::selectRaw('reviewer_id, COUNT(*) as review_count')
            ->whereHasMorph('reviewable', [Idea::class])
            ->groupBy('reviewer_id')
            ->get()
            ->keyBy('reviewer_id');

        $challengeReviewers = ChallengeReview::selectRaw('reviewer_id, COUNT(*) as review_count')
            ->groupBy('reviewer_id')
            ->get()
            ->keyBy('reviewer_id');

        $allReviewers = collect();

        // Combine idea and challenge reviews
        foreach ($ideaReviewers as $reviewerId => $data) {
            $totalReviews = $data->review_count + ($challengeReviewers[$reviewerId]->review_count ?? 0);
            $allReviewers->put($reviewerId, $totalReviews);
        }

        foreach ($challengeReviewers as $reviewerId => $data) {
            if (!$allReviewers->has($reviewerId)) {
                $allReviewers->put($reviewerId, $data->review_count);
            }
        }

        return $allReviewers
            ->sortDesc()
            ->take($limit)
            ->map(function ($reviewCount, $userId) {
                $user = User::find($userId);
                return [
                    'user' => $user,
                    'review_count' => $reviewCount,
                    'department' => $user->staff?->department ?? 'External',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate review performance metrics
     */
    public function calculateReviewPerformance(User $user, string $period = 'monthly'): array
    {
        $query = \App\Models\UserPoint::where('user_id', $user->id)
            ->where('action', 'review_completion');

        if ($period === 'monthly') {
            $query->whereYear('created_at', now()->year)
                  ->whereMonth('created_at', now()->month);
        } elseif ($period === 'yearly') {
            $query->whereYear('created_at', now()->year);
        }

        $reviewPoints = $query->get();
        
        return [
            'total_review_points' => $reviewPoints->sum('points'),
            'review_count' => $reviewPoints->where('description', 'not like', '%bonus%')->count(),
            'bonus_count' => $reviewPoints->where('description', 'like', '%bonus%')->count(),
            'early_reviews' => $reviewPoints->where('description', 'like', '%Early review bonus%')->count(),
            'first_half_bonuses' => $reviewPoints->where('description', 'like', '%First half reviewer%')->count(),
        ];
    }

    /**
     * Send point notification
     */
    private function sendPointNotification(User $user, $pointRecord): void
    {
        \App\Models\AppNotification::create([
            'user_id' => $user->id,
            'type' => 'points_awarded',
            'title' => 'Review Points Earned!',
            'message' => "You earned {$pointRecord->points} points: {$pointRecord->description}",
            'related_type' => get_class($pointRecord),
            'related_id' => $pointRecord->id,
        ]);
    }
}
