<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * KeNHAVATE Daily Login Tracking Service
 * Handles daily login points with 24hr cooldown and streak tracking
 */
class DailyLoginService
{
    private GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Process daily login for user
     */
    public function processLogin(User $user): ?UserPoint
    {
        // Check if already logged today
        if ($this->hasLoggedToday($user)) {
            return null;
        }

        // Award daily login points
        return $this->gamificationService->awardDailySignIn($user);
    }

    /**
     * Check if user has logged in today
     */
    public function hasLoggedToday(User $user): bool
    {
        $cacheKey = "daily_login_" . $user->id . "_" . today()->format('Y-m-d');
        
        return Cache::remember($cacheKey, 86400, function () use ($user) {
            return UserPoint::where('user_id', $user->id)
                ->where('action', 'daily_login')
                ->whereDate('created_at', today())
                ->exists();
        });
    }

    /**
     * Get user's current login streak
     */
    public function getLoginStreak(User $user): int
    {
        $cacheKey = "login_streak_" . $user->id;
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
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
        });
    }

    /**
     * Get login streak with current day if user logged today
     */
    public function getCurrentStreak(User $user): int
    {
        $streak = $this->getLoginStreak($user);
        
        if ($this->hasLoggedToday($user)) {
            $streak++;
        }
        
        return $streak;
    }

    /**
     * Get login statistics for user
     */
    public function getLoginStats(User $user): array
    {
        $totalLogins = UserPoint::where('user_id', $user->id)
            ->where('action', 'daily_login')
            ->count();

        $thisMonthLogins = UserPoint::where('user_id', $user->id)
            ->where('action', 'daily_login')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $currentStreak = $this->getCurrentStreak($user);
        
        // Calculate longest streak
        $longestStreak = $this->getLongestStreak($user);

        return [
            'total_logins' => $totalLogins,
            'this_month_logins' => $thisMonthLogins,
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'logged_today' => $this->hasLoggedToday($user),
        ];
    }

    /**
     * Get user's longest login streak
     */
    private function getLongestStreak(User $user): int
    {
        $loginDates = UserPoint::where('user_id', $user->id)
            ->where('action', 'daily_login')
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->unique()
            ->values()
            ->toArray();

        if (empty($loginDates)) {
            return 0;
        }

        $longestStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($loginDates); $i++) {
            $currentDate = Carbon::parse($loginDates[$i]);
            $previousDate = Carbon::parse($loginDates[$i - 1]);

            if ($currentDate->diffInDays($previousDate) === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $longestStreak;
    }

    /**
     * Clear login cache for user
     */
    public function clearCache(User $user): void
    {
        $todayKey = "daily_login_" . $user->id . "_" . today()->format('Y-m-d');
        $streakKey = "login_streak_" . $user->id;
        
        Cache::forget($todayKey);
        Cache::forget($streakKey);
    }

    /**
     * Get users who haven't logged in today for reminder notifications
     */
    public function getUsersForReminder(): array
    {
        return User::whereDoesntHave('points', function ($query) {
                $query->where('action', 'daily_login')
                      ->whereDate('created_at', today());
            })
            ->where('account_status', 'active')
            ->with('staff')
            ->get()
            ->toArray();
    }
}
