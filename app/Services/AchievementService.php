<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoint;
use App\Models\AppNotification;
use Illuminate\Support\Facades\Cache;

/**
 * KeNHAVATE Achievement System
 * Handles achievement tracking, badge awards, and milestone recognition
 */
class AchievementService
{
    private GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    // Achievement definitions with criteria
    const ACHIEVEMENTS = [
        'innovation_pioneer' => [
            'name' => 'Innovation Pioneer',
            'description' => 'Submit your first 10 ideas',
            'badge' => 'bronze',
            'points_bonus' => 100,
            'criteria' => 10,
            'type' => 'idea_count',
        ],
        'collaboration_champion' => [
            'name' => 'Collaboration Champion',
            'description' => 'Participate in 50+ active collaborations',
            'badge' => 'gold',
            'points_bonus' => 300,
            'criteria' => 50,
            'type' => 'collaboration_count',
        ],
        'quick_reviewer' => [
            'name' => 'Quick Reviewer',
            'description' => 'Complete 20 reviews within 24 hours',
            'badge' => 'silver',
            'points_bonus' => 200,
            'criteria' => 20,
            'type' => 'fast_review_count',
        ],
        'challenge_master' => [
            'name' => 'Challenge Master',
            'description' => 'Win 3+ challenges',
            'badge' => 'platinum',
            'points_bonus' => 500,
            'criteria' => 3,
            'type' => 'challenge_wins',
        ],
        'idea_implementer' => [
            'name' => 'Idea Implementer',
            'description' => '5+ ideas reach implementation stage',
            'badge' => 'diamond',
            'points_bonus' => 1000,
            'criteria' => 5,
            'type' => 'implemented_ideas',
        ],
        'community_builder' => [
            'name' => 'Community Builder',
            'description' => 'Invite 10+ successful collaborators',
            'badge' => 'gold',
            'points_bonus' => 400,
            'criteria' => 10,
            'type' => 'successful_invitations',
        ],
        'consistent_contributor' => [
            'name' => 'Consistent Contributor',
            'description' => 'Maintain a 30-day login streak',
            'badge' => 'silver',
            'points_bonus' => 250,
            'criteria' => 30,
            'type' => 'login_streak',
        ],
        'weekend_warrior' => [
            'name' => 'Weekend Warrior',
            'description' => 'Complete 10+ weekend activities',
            'badge' => 'bronze',
            'points_bonus' => 150,
            'criteria' => 10,
            'type' => 'weekend_activities',
        ],
        'review_expert' => [
            'name' => 'Review Expert',
            'description' => 'Complete 100+ reviews',
            'badge' => 'gold',
            'points_bonus' => 350,
            'criteria' => 100,
            'type' => 'total_reviews',
        ],
        'innovation_catalyst' => [
            'name' => 'Innovation Catalyst',
            'description' => 'Reach 10,000 total points',
            'badge' => 'platinum',
            'points_bonus' => 1000,
            'criteria' => 10000,
            'type' => 'total_points',
        ],
    ];

    /**
     * Check and award achievements for user
     */
    public function checkAllAchievements(User $user): array
    {
        $newAchievements = [];

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            if ($this->checkAchievement($user, $key)) {
                $newAchievements[] = $achievement;
            }
        }

        return $newAchievements;
    }

    /**
     * Alias for checkAllAchievements for test compatibility
     */
    public function checkAchievements(User $user): array
    {
        return $this->checkAllAchievements($user);
    }

    /**
     * Check specific achievement for user
     */
    public function checkAchievement(User $user, string $achievementKey): bool
    {
        $achievement = self::ACHIEVEMENTS[$achievementKey] ?? null;
        
        if (!$achievement) {
            return false;
        }

        // Check if already awarded
        if ($this->hasAchievement($user, $achievementKey)) {
            return false;
        }

        // Check if user meets criteria
        $currentValue = $this->getUserProgressValue($user, $achievement['type']);
        
        if ($currentValue >= $achievement['criteria']) {
            $this->awardAchievement($user, $achievementKey, $achievement);
            return true;
        }

        return false;
    }

    /**
     * Award achievement to user
     */
    private function awardAchievement(User $user, string $achievementKey, array $achievement): void
    {
        // Create achievement record
        $points = $this->gamificationService->createUserPoint(
            $user,
            'achievement_unlocked',
            $achievement['points_bonus'],
            "Achievement unlocked: {$achievement['name']} - {$achievement['description']}",
            null,
            null,
            $achievementKey
        );

        // Create achievement notification
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'achievement_unlocked',
            'title' => '🏆 Achievement Unlocked!',
            'message' => "Congratulations! You've earned the '{$achievement['name']}' {$achievement['badge']} badge and {$achievement['points_bonus']} bonus points!",
            'related_type' => UserPoint::class,
            'related_id' => $points->id,
        ]);

        // Clear achievement cache
        $this->clearAchievementCache($user);
    }

    /**
     * Check if user has specific achievement
     */
    public function hasAchievement(User $user, string $achievementKey): bool
    {
        return UserPoint::where('user_id', $user->id)
            ->where('action', 'achievement_unlocked')
            ->where('description', 'like', "%{$achievementKey}%")
            ->exists();
    }

    /**
     * Get user's current progress value for achievement type
     */
    private function getUserProgressValue(User $user, string $type): int
    {
        return match($type) {
            'idea_count' => $user->ideas()->count(),
            'collaboration_count' => $user->collaborations()->count(),
            'fast_review_count' => $this->getFastReviewCount($user),
            'challenge_wins' => UserPoint::where('user_id', $user->id)
                ->where('action', 'challenge_winner')
                ->count(),
            'implemented_ideas' => $user->ideas()
                ->where('current_stage', 'implementation')
                ->orWhere('current_stage', 'completed')
                ->count(),
            'successful_invitations' => $this->getSuccessfulInvitations($user),
            'login_streak' => app(DailyLoginService::class)->getCurrentStreak($user),
            'weekend_activities' => UserPoint::where('user_id', $user->id)
                ->where('description', 'like', '%weekend%')
                ->count(),
            'total_reviews' => $this->getTotalReviewCount($user),
            'total_points' => $user->totalPoints(),
            default => 0,
        };
    }

    /**
     * Get fast review count for user
     */
    private function getFastReviewCount(User $user): int
    {
        return UserPoint::where('user_id', $user->id)
            ->where('action', 'review_completion')
            ->where('description', 'like', '%Early review bonus%')
            ->count();
    }

    /**
     * Get successful collaboration invitations count
     */
    private function getSuccessfulInvitations(User $user): int
    {
        // This would be implemented based on collaboration invitation tracking
        // For now, return count of collaboration acceptances
        return UserPoint::where('user_id', $user->id)
            ->where('action', 'collaboration_accepted')
            ->count();
    }

    /**
     * Get total review count for user
     */
    private function getTotalReviewCount(User $user): int
    {
        $ideaReviews = \App\Models\Review::where('reviewer_id', $user->id)->count();
        $challengeReviews = \App\Models\ChallengeReview::where('reviewer_id', $user->id)->count();
        
        return $ideaReviews + $challengeReviews;
    }

    /**
     * Get all achievements with user's progress
     */
    public function getUserAchievements(User $user): array
    {
        $cacheKey = "user_achievements_{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $achievements = [];
            
            foreach (self::ACHIEVEMENTS as $key => $achievement) {
                $currentValue = $this->getUserProgressValue($user, $achievement['type']);
                $hasAchievement = $this->hasAchievement($user, $key);
                $progress = min(100, ($currentValue / $achievement['criteria']) * 100);
                
                $achievements[] = [
                    'key' => $key,
                    'name' => $achievement['name'],
                    'description' => $achievement['description'],
                    'badge' => $achievement['badge'],
                    'points_bonus' => $achievement['points_bonus'],
                    'criteria' => $achievement['criteria'],
                    'current_value' => $currentValue,
                    'progress_percentage' => round($progress, 1),
                    'achieved' => $hasAchievement,
                    'achieved_at' => $hasAchievement ? $this->getAchievementDate($user, $key) : null,
                ];
            }
            
            return $achievements;
        });
    }

    /**
     * Get achievement unlock date
     */
    private function getAchievementDate(User $user, string $achievementKey): ?string
    {
        $point = UserPoint::where('user_id', $user->id)
            ->where('action', 'achievement_unlocked')
            ->where('description', 'like', "%{$achievementKey}%")
            ->first();
            
        return $point?->created_at?->format('Y-m-d H:i:s');
    }

    /**
     * Get achievement statistics
     */
    public function getAchievementStats(User $user): array
    {
        $achievements = $this->getUserAchievements($user);
        $earned = collect($achievements)->where('achieved', true);
        
        return [
            'total_achievements' => count($achievements),
            'earned_achievements' => $earned->count(),
            'completion_percentage' => count($achievements) > 0 
                ? round(($earned->count() / count($achievements)) * 100, 1) 
                : 0,
            'total_achievement_points' => $earned->sum('points_bonus'),
            'badges_by_type' => $earned->groupBy('badge')->map->count()->toArray(),
        ];
    }

    /**
     * Get achievement leaderboard
     */
    public function getAchievementLeaderboard(int $limit = 10): array
    {
        return User::withCount(['points as achievement_count' => function ($query) {
                $query->where('action', 'achievement_unlocked');
            }])
            ->withSum(['points as achievement_points' => function ($query) {
                $query->where('action', 'achievement_unlocked');
            }], 'points')
            ->having('achievement_count', '>', 0)
            ->orderByDesc('achievement_count')
            ->orderByDesc('achievement_points')
            ->with('staff')
            ->limit($limit)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $user,
                    'achievement_count' => $user->achievement_count,
                    'achievement_points' => $user->achievement_points ?? 0,
                    'department' => $user->staff?->department ?? 'External',
                ];
            })
            ->toArray();
    }

    /**
     * Clear achievement cache for user
     */
    private function clearAchievementCache(User $user): void
    {
        Cache::forget("user_achievements_{$user->id}");
    }

    /**
     * Get badge icons
     */
    public function getBadgeIcon(string $badge): string
    {
        return match($badge) {
            'bronze' => '🥉',
            'silver' => '🥈',
            'gold' => '🥇',
            'platinum' => '💎',
            'diamond' => '💍',
            default => '🏆',
        };
    }

    /**
     * Get badge color class
     */
    public function getBadgeColorClass(string $badge): string
    {
        return match($badge) {
            'bronze' => 'text-amber-600 bg-amber-100',
            'silver' => 'text-gray-600 bg-gray-100',
            'gold' => 'text-yellow-600 bg-yellow-100',
            'platinum' => 'text-indigo-600 bg-indigo-100',
            'diamond' => 'text-purple-600 bg-purple-100',
            default => 'text-blue-600 bg-blue-100',
        };
    }

    /**
     * Get achievement distribution for admin analytics
     */
    public function getAchievementDistribution(): array
    {
        $distribution = [];
        
        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            $count = Cache::remember("achievement_distribution_{$key}", 300, function() use ($key) {
                // Count users who have achieved this milestone
                return $this->getUsersWithAchievement($key)->count();
            });
            
            $distribution[$key] = [
                'name' => $achievement['name'],
                'count' => $count,
                'badge' => $achievement['badge'],
                'description' => $achievement['description'],
            ];
        }
        
        return $distribution;
    }

    /**
     * Get users who have achieved a specific achievement
     */
    private function getUsersWithAchievement(string $achievementKey): \Illuminate\Database\Eloquent\Collection
    {
        $achievement = self::ACHIEVEMENTS[$achievementKey] ?? null;
        if (!$achievement) {
            return collect();
        }

        return User::whereHas('userPoints', function($query) use ($achievement, $achievementKey) {
            $query->where('action', 'achievement_' . $achievementKey);
        })->get();
    }
}
