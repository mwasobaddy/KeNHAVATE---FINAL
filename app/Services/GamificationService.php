<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoint;
use App\Models\AppNotification;
use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;
use App\Models\ChallengeReview;
use App\Models\ChallengeSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * KeNHAVATE Innovation Portal - Comprehensive Gamification Service
 * Handles all point calculations, awards, and achievement tracking
 */
class GamificationService
{
    // Point values as defined in PRD
    const POINTS = [
        'first_time_signup' => 50,
        'daily_sign_in' => 5,
        'idea_submission' => 100,
        'challenge_participation' => 75,
        'collaboration_accepted' => 25,
        'collaboration_contribution' => 30,
        'first_half_reviewer_ideas' => 1,
        'first_half_reviewer_challenges' => 1,
        'first_half_reviewer_idea' => 1,      // Added for test compatibility
        'first_half_reviewer_challenge' => 1, // Added for test compatibility
        'early_review_bonus' => 15,
        'quick_review_streak' => 10,
        'idea_approved' => 200,
        'challenge_winner' => 500,
        'login_streak' => 5,                  // Added for test compatibility
        'login_streak_5' => 5,
        'login_streak_10' => 10,
        'login_streak_15' => 15,
        'login_streak_20' => 20,
        'login_streak_25' => 25,
        'mentor_bonus' => 40,
        'innovation_milestone' => 100,
        'feedback_quality' => 20,
        'weekend_warrior' => 5,
        'department_champion' => 150,
        'consistency_master' => 300,
    ];

    /**
     * Award first time signup bonus
     */
    public function awardFirstTimeSignup(User $user): UserPoint
    {
        // Check if already awarded
        $existing = UserPoint::where('user_id', $user->id)
            ->where('action', 'account_creation')
            ->first();

        if ($existing) {
            return $existing;
        }

        $points = $this->createUserPoint(
            $user,
            'account_creation',
            self::POINTS['first_time_signup'],
            'Welcome to KeNHAVATE! Bonus for creating your account.',
            $user
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award daily sign in points (with 24hr cooldown)
     */
    public function awardDailySignIn(User $user): ?UserPoint
    {
        $today = Carbon::today();
        
        // Check if already awarded today
        $existingToday = UserPoint::where('user_id', $user->id)
            ->where('action', 'daily_login')
            ->whereDate('created_at', $today)
            ->first();

        if ($existingToday) {
            return null; // Already awarded today
        }

        // Calculate streak bonus
        $streakDays = $this->calculateLoginStreak($user);
        $basePoints = self::POINTS['daily_sign_in'];
        $streakBonus = $this->getStreakBonus($streakDays);
        
        $totalPoints = $basePoints + $streakBonus;
        $description = "Daily login bonus";
        
        if ($streakBonus > 0) {
            $description .= " with {$streakDays}-day streak bonus (+{$streakBonus} points)";
        }

        $points = $this->createUserPoint(
            $user,
            'daily_login',
            $totalPoints,
            $description
        );

        // Check for milestone achievements
        $this->checkLoginStreakAchievements($user, $streakDays);
        
        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award idea submission points
     */
    public function awardIdeaSubmission(User $user, Idea $idea): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'idea_submission',
            self::POINTS['idea_submission'],
            "Points for submitting idea: {$idea->title}",
            $idea
        );

        // Check for innovation milestones
        $this->checkInnovationMilestone($user);
        
        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award challenge participation points
     */
    public function awardChallengeParticipation(User $user, ChallengeSubmission $submission): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'challenge_participation',
            self::POINTS['challenge_participation'],
            "Points for participating in challenge: {$submission->challenge->title}",
            $submission
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award collaboration acceptance points
     */
    public function awardCollaborationAccepted(User $user, $collaboration): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'collaboration_accepted',
            self::POINTS['collaboration_accepted'],
            'Points for accepting collaboration invitation',
            $collaboration
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award collaboration contribution points
     */
    public function awardCollaborationContribution(User $user, $collaboration): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'collaboration_contribution',
            self::POINTS['collaboration_contribution'],
            'Points for active collaboration contribution',
            $collaboration
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award first half reviewer bonus for ideas
     */
    public function awardFirstHalfReviewerBonus(User $user, Review $review): ?UserPoint
    {
        $idea = $review->reviewable;
        if (!$idea instanceof Idea) {
            return null;
        }

        // Get all reviews for this idea, ordered by creation time
        $allReviews = Review::where('reviewable_type', Idea::class)
            ->where('reviewable_id', $idea->id)
            ->orderBy('created_at')
            ->get();

        $totalReviews = $allReviews->count();
        $reviewPosition = $allReviews->search(function($r) use ($review) {
            return $r->id === $review->id;
        }) + 1;

        // Award bonus if in first 50% of reviewers
        if ($reviewPosition <= ($totalReviews / 2)) {
            $points = $this->createUserPoint(
                $user,
                'review_completion',
                self::POINTS['first_half_reviewer_ideas'],
                "First half reviewer bonus for idea: {$idea->title} (Position: {$reviewPosition}/{$totalReviews})",
                $review,
                null,
                'first_half_reviewer'
            );

            $this->sendPointNotification($user, $points);
            
            return $points;
        }

        return null;
    }

    /**
     * Award first half reviewer bonus for challenges
     */
    public function awardFirstHalfChallengeReviewerBonus(User $user, ChallengeReview $review): ?UserPoint
    {
        $submission = $review->submission;
        
        // Get all reviews for this challenge submission, ordered by creation time
        $allReviews = ChallengeReview::where('challenge_submission_id', $submission->id)
            ->orderBy('created_at')
            ->get();

        $totalReviews = $allReviews->count();
        $reviewPosition = $allReviews->search(function($r) use ($review) {
            return $r->id === $review->id;
        }) + 1;

        // Award bonus if in first 50% of reviewers
        if ($reviewPosition <= ($totalReviews / 2)) {
            $points = $this->createUserPoint(
                $user,
                'review_completion',
                self::POINTS['first_half_reviewer_challenges'],
                "First half reviewer bonus for challenge submission (Position: {$reviewPosition}/{$totalReviews})",
                $review,
                null,
                'first_half_reviewer'
            );

            $this->sendPointNotification($user, $points);
            
            return $points;
        }

        return null;
    }

    /**
     * Award early review bonus (within 24 hours)
     */
    public function awardEarlyReviewBonus(User $user, $review, $submissionTime): ?UserPoint
    {
        $reviewTime = $review->created_at;
        $timeDiff = $reviewTime->diffInHours($submissionTime);

        if ($timeDiff <= 24) {
            $points = $this->createUserPoint(
                $user,
                'review_completion',
                self::POINTS['early_review_bonus'],
                "Early review bonus - completed within {$timeDiff} hours",
                $review,
                null,
                'early_review'
            );

            $this->sendPointNotification($user, $points);
            
            return $points;
        }

        return null;
    }

    /**
     * Award idea approval bonus
     */
    public function awardIdeaApproved(User $user, Idea $idea): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'idea_approved',
            self::POINTS['idea_approved'],
            "Bonus for idea reaching implementation: {$idea->title}",
            $idea
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award challenge winner points
     */
    public function awardChallengeWinner(User $user, Challenge $challenge, int $position = 1): UserPoint
    {
        $basePoints = self::POINTS['challenge_winner'];
        
        // Adjust points based on position
        $pointMultipliers = [1 => 1.0, 2 => 0.7, 3 => 0.5];
        $finalPoints = (int) ($basePoints * ($pointMultipliers[$position] ?? 0.3));
        
        $positionText = match($position) {
            1 => '1st place',
            2 => '2nd place', 
            3 => '3rd place',
            default => "{$position}th place"
        };

        $points = $this->createUserPoint(
            $user,
            'challenge_winner',
            $finalPoints,
            "Challenge winner ({$positionText}): {$challenge->title}",
            $challenge
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Award weekend activity bonus
     */
    public function awardWeekendWarrior(User $user, $activity): UserPoint
    {
        $points = $this->createUserPoint(
            $user,
            'weekend_activity',
            self::POINTS['weekend_warrior'],
            'Weekend warrior bonus for weekend activity',
            $activity,
            null,
            'weekend_warrior'
        );

        $this->sendPointNotification($user, $points);
        
        return $points;
    }

    /**
     * Check if current activity qualifies for weekend bonus
     */
    public function checkWeekendBonus(User $user, $activity): ?UserPoint
    {
        $now = Carbon::now();
        
        if ($now->isWeekend()) {
            return $this->awardWeekendWarrior($user, $activity);
        }
        
        return null;
    }

    /**
     * Calculate user's current login streak
     */
    private function calculateLoginStreak(User $user): int
    {
        $streak = 0;
        $checkDate = Carbon::yesterday();
        
        while (true) {
            $hasLogin = UserPoint::where('user_id', $user->id)
                ->where('action', 'daily_login')
                ->whereDate('created_at', $checkDate)
                ->exists();
                
            if (!$hasLogin) {
                break;
            }
            
            $streak++;
            $checkDate = $checkDate->subDay();
        }
        
        return $streak;
    }

    /**
     * Get streak bonus based on consecutive days
     */
    private function getStreakBonus(int $streakDays): int
    {
        if ($streakDays >= 25) return self::POINTS['login_streak_25'] - self::POINTS['daily_sign_in'];
        if ($streakDays >= 20) return self::POINTS['login_streak_20'] - self::POINTS['daily_sign_in'];
        if ($streakDays >= 15) return self::POINTS['login_streak_15'] - self::POINTS['daily_sign_in'];
        if ($streakDays >= 10) return self::POINTS['login_streak_10'] - self::POINTS['daily_sign_in'];
        if ($streakDays >= 5) return self::POINTS['login_streak_5'] - self::POINTS['daily_sign_in'];
        
        return 0;
    }

    /**
     * Check for innovation milestone achievements (every 5th idea)
     */
    private function checkInnovationMilestone(User $user): void
    {
        $ideaCount = $user->ideas()->count();
        
        if ($ideaCount > 0 && $ideaCount % 5 === 0) {
            $points = $this->createUserPoint(
                $user,
                'innovation_milestone',
                self::POINTS['innovation_milestone'],
                "Innovation milestone reached: {$ideaCount} ideas submitted!",
                null,
                null,
                'innovation_milestone'
            );

            $this->sendPointNotification($user, $points);
        }
    }

    /**
     * Check for login streak achievements
     */
    private function checkLoginStreakAchievements(User $user, int $streakDays): void
    {
        // Check for 30-day consistency master achievement
        if ($streakDays === 30) {
            $points = $this->createUserPoint(
                $user,
                'consistency_achievement',
                self::POINTS['consistency_master'],
                "Consistency Master: 30-day login streak achieved!",
                null,
                null,
                'consistency_master'
            );

            $this->sendPointNotification($user, $points);
        }
    }

    /**
     * Create a UserPoint record
     */
    private function createUserPoint(
        User $user,
        string $actionType,
        int $points,
        string $description,
        $relatedEntity = null,
        ?float $multiplier = null,
        ?string $bonusReason = null
    ): UserPoint {
        return UserPoint::create([
            'user_id' => $user->id,
            'action' => $actionType,
            'points' => $points,
            'description' => $description,
            'related_type' => $relatedEntity ? get_class($relatedEntity) : null,
            'related_id' => $relatedEntity?->id,
        ]);
    }

    /**
     * Send point notification to user
     */
    private function sendPointNotification(User $user, UserPoint $pointRecord): void
    {
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'points_awarded',
            'title' => 'Points Earned!',
            'message' => "You earned {$pointRecord->points} points: {$pointRecord->description}",
            'related_type' => UserPoint::class,
            'related_id' => $pointRecord->id,
        ]);
    }

    /**
     * Get user's total points
     */
    public function getUserTotalPoints(User $user): int
    {
        return $user->points()->sum('points');
    }

    /**
     * Get user's monthly points
     */
    public function getUserMonthlyPoints(User $user): int
    {
        return $user->points()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('points');
    }

    /**
     * Get user's yearly points
     */
    public function getUserYearlyPoints(User $user): int
    {
        return $user->points()
            ->whereYear('created_at', now()->year)
            ->sum('points');
    }

    /**
     * Get leaderboard data
     */
    public function getLeaderboard(string $period = 'all', int $limit = 10): array
    {
        $query = User::with('points', 'staff')
            ->withSum(['points' => function($query) use ($period) {
                if ($period === 'monthly') {
                    $query->whereYear('created_at', now()->year)
                          ->whereMonth('created_at', now()->month);
                } elseif ($period === 'yearly') {
                    $query->whereYear('created_at', now()->year);
                } elseif ($period === 'weekly') {
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                }
            }], 'points')
            ->having('points_sum_points', '>', 0)
            ->orderByDesc('points_sum_points')
            ->limit($limit);

        return $query->get()->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user' => $user,
                'total_points' => $user->points_sum_points ?? 0,
                'department' => $user->staff?->department ?? 'External',
            ];
        })->toArray();
    }

    /**
     * Get department leaderboard
     */
    public function getDepartmentLeaderboard(string $department, string $period = 'all', int $limit = 10): array
    {
        $query = User::with('points', 'staff')
            ->whereHas('staff', function($q) use ($department) {
                $q->where('department', $department);
            })
            ->withSum(['points' => function($query) use ($period) {
                if ($period === 'monthly') {
                    $query->whereYear('created_at', now()->year)
                          ->whereMonth('created_at', now()->month);
                } elseif ($period === 'yearly') {
                    $query->whereYear('created_at', now()->year);
                }
            }], 'points')
            ->having('points_sum_points', '>', 0)
            ->orderByDesc('points_sum_points')
            ->limit($limit);

        return $query->get()->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user' => $user,
                'total_points' => $user->points_sum_points ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get role-based leaderboard
     */
    public function getRoleBasedLeaderboard(string $period = 'all', int $limit = 10, ?string $specificRole = null): array
    {
        $query = User::with('points', 'staff', 'roles')
            ->withSum(['points' => function($query) use ($period) {
                if ($period === 'monthly') {
                    $query->whereYear('created_at', now()->year)
                          ->whereMonth('created_at', now()->month);
                } elseif ($period === 'yearly') {
                    $query->whereYear('created_at', now()->year);
                } elseif ($period === 'weekly') {
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                }
            }], 'points');

        // Filter by specific role if provided
        if ($specificRole) {
            $query->whereHas('roles', function($q) use ($specificRole) {
                $q->where('name', $specificRole);
            });
        }

        $users = $query->having('points_sum_points', '>', 0)
            ->orderByDesc('points_sum_points')
            ->limit($limit)
            ->get();

        return $users->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user' => $user,
                'total_points' => $user->points_sum_points ?? 0,
                'role' => $user->roles->first()?->name ?? 'user',
                'department' => $user->staff?->department ?? 'External',
            ];
        })->toArray();
    }

    /**
     * Get user achievements
     */
    public function getUserAchievements(User $user): array
    {
        $totalPoints = $this->getUserTotalPoints($user);
        $totalIdeas = $user->ideas()->count();
        $totalCollaborations = $user->collaborations()->count();
        $loginStreak = $this->calculateLoginStreak($user);
        $challengeWins = UserPoint::where('user_id', $user->id)
            ->where('action', 'challenge_winner')
            ->count();

        $achievements = [];

        // Innovation Pioneer
        if ($totalIdeas >= 10) {
            $achievements[] = [
                'name' => 'Innovation Pioneer',
                'description' => 'Submit first 10 ideas',
                'badge' => 'bronze',
                'achieved' => true,
                'progress' => 100,
            ];
        } else {
            $achievements[] = [
                'name' => 'Innovation Pioneer',
                'description' => 'Submit first 10 ideas',
                'badge' => 'bronze',
                'achieved' => false,
                'progress' => ($totalIdeas / 10) * 100,
            ];
        }

        // Collaboration Champion
        if ($totalCollaborations >= 50) {
            $achievements[] = [
                'name' => 'Collaboration Champion',
                'description' => '50+ active collaborations',
                'badge' => 'gold',
                'achieved' => true,
                'progress' => 100,
            ];
        } else {
            $achievements[] = [
                'name' => 'Collaboration Champion',
                'description' => '50+ active collaborations',
                'badge' => 'gold',
                'achieved' => false,
                'progress' => ($totalCollaborations / 50) * 100,
            ];
        }

        // Challenge Master
        if ($challengeWins >= 3) {
            $achievements[] = [
                'name' => 'Challenge Master',
                'description' => 'Win 3+ challenges',
                'badge' => 'platinum',
                'achieved' => true,
                'progress' => 100,
            ];
        } else {
            $achievements[] = [
                'name' => 'Challenge Master',
                'description' => 'Win 3+ challenges',
                'badge' => 'platinum',
                'achieved' => false,
                'progress' => ($challengeWins / 3) * 100,
            ];
        }

        // Consistent Contributor
        if ($loginStreak >= 30) {
            $achievements[] = [
                'name' => 'Consistent Contributor',
                'description' => '30-day login streak',
                'badge' => 'silver',
                'achieved' => true,
                'progress' => 100,
            ];
        } else {
            $achievements[] = [
                'name' => 'Consistent Contributor',
                'description' => '30-day login streak',
                'badge' => 'silver',
                'achieved' => false,
                'progress' => ($loginStreak / 30) * 100,
            ];
        }

        return $achievements;
    }

    /**
     * Get points breakdown for user
     */
    public function getPointsBreakdown(User $user): array
    {
        return $user->points()
            ->selectRaw('action, SUM(points) as total_points, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('total_points')
            ->get()
            ->map(function($item) {
                return [
                    'action' => $item->action,
                    'total_points' => $item->total_points,
                    'count' => $item->count,
                    'description' => $this->getActionDescription($item->action),
                ];
            })->toArray();
    }

    /**
     * Get action description
     */
    private function getActionDescription(string $actionType): string
    {
        return match($actionType) {
            'account_creation' => 'Account Creation Bonus',
            'daily_login' => 'Daily Login Points',
            'idea_submission' => 'Idea Submissions',
            'challenge_participation' => 'Challenge Participation',
            'collaboration_accepted' => 'Collaboration Invitations Accepted',
            'collaboration_contribution' => 'Collaboration Contributions',
            'review_completion' => 'Review Completions & Bonuses',
            'idea_approved' => 'Ideas Approved for Implementation',
            'challenge_winner' => 'Challenge Wins',
            'innovation_milestone' => 'Innovation Milestones',
            'weekend_activity' => 'Weekend Activity Bonuses',
            'consistency_achievement' => 'Consistency Achievements',
            default => ucwords(str_replace('_', ' ', $actionType)),
        };
    }

    /**
     * Generic method to award points for any action
     */
    public function awardPoints(User $user, string $action, int $points, string $description = '', $relatedEntity = null): UserPoint
    {
        return $this->createUserPoint($user, $action, $points, $description, $relatedEntity);
    }

    /**
     * Check and award achievements for user
     */
    public function checkAchievements(User $user): array
    {
        $achievementService = app(AchievementService::class);
        return $achievementService->checkAchievements($user);
    }

    /**
     * Award login streak bonus
     */
    public function awardLoginStreak(User $user, int $streakDays): ?UserPoint
    {
        $streakBonusPoints = $this->getStreakBonus($streakDays);
        
        if ($streakBonusPoints <= 0) {
            return null;
        }

        return $this->createUserPoint(
            $user,
            'login_streak',
            $streakBonusPoints,
            "Login streak bonus for {$streakDays} consecutive days",
            null
        );
    }
}
