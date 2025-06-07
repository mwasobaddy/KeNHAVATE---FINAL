<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Get comprehensive system overview metrics
     */
    public function getSystemOverview(): array
    {
        return [
            'totals' => [
                'users' => User::count(),
                'ideas' => Idea::count(),
                'challenges' => Challenge::count(),
                'reviews' => Review::count(),
                'points_awarded' => UserPoint::sum('points'),
            ],
            'current_month' => [
                'new_users' => User::whereMonth('created_at', now()->month)->count(),
                'new_ideas' => Idea::whereMonth('created_at', now()->month)->count(),
                'new_challenges' => Challenge::whereMonth('created_at', now()->month)->count(),
                'reviews_completed' => Review::whereMonth('completed_at', now()->month)->count(),
                'points_awarded' => UserPoint::whereMonth('created_at', now()->month)->sum('points'),
            ],
            'growth_rates' => $this->calculateGrowthRates(),
        ];
    }

    /**
     * Get idea workflow analytics
     */
    public function getIdeaWorkflowAnalytics(): array
    {
        $ideaStages = Idea::select('current_stage', DB::raw('count(*) as count'))
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        $avgProcessingTime = $this->calculateAverageProcessingTime();
        $conversionRates = $this->calculateConversionRates();
        $monthlyTrends = $this->getMonthlyIdeaTrends();

        return [
            'stage_distribution' => $ideaStages,
            'processing_times' => $avgProcessingTime,
            'conversion_rates' => $conversionRates,
            'monthly_trends' => $monthlyTrends,
            'bottlenecks' => $this->identifyBottlenecks(),
        ];
    }

    /**
     * Get user engagement analytics
     */
    public function getUserEngagementAnalytics(): array
    {
        return [
            'active_users' => [
                'daily' => $this->getActiveUsers(1),
                'weekly' => $this->getActiveUsers(7),
                'monthly' => $this->getActiveUsers(30),
            ],
            'role_distribution' => $this->getRoleDistribution(),
            'engagement_trends' => $this->getEngagementTrends(),
            'top_contributors' => $this->getTopContributors(),
            'collaboration_metrics' => $this->getCollaborationMetrics(),
        ];
    }

    /**
     * Get performance analytics by role
     */
    public function getPerformanceAnalytics(): array
    {
        return [
            'review_performance' => $this->getReviewPerformance(),
            'department_metrics' => $this->getDepartmentMetrics(),
            'innovation_scores' => $this->getInnovationScores(),
            'productivity_trends' => $this->getProductivityTrends(),
        ];
    }

    /**
     * Get challenge analytics
     */
    public function getChallengeAnalytics(): array
    {
        return [
            'participation_rates' => $this->getChallengeParticipation(),
            'completion_rates' => $this->getChallengeCompletion(),
            'challenge_trends' => $this->getChallengeTrends(),
            'winner_analysis' => $this->getChallengeWinnerAnalysis(),
        ];
    }

    /**
     * Get gamification analytics
     */
    public function getGamificationAnalytics(): array
    {
        return [
            'point_distribution' => $this->getPointDistribution(),
            'achievement_stats' => $this->getAchievementStats(),
            'leaderboard_trends' => $this->getLeaderboardTrends(),
            'engagement_correlation' => $this->getEngagementCorrelation(),
        ];
    }

    /**
     * Generate custom report data
     */
    public function generateCustomReport(array $filters): array
    {
        $dateRange = $this->parseDateRange($filters['date_range'] ?? 'last_30_days');
        $roles = $filters['roles'] ?? [];
        $departments = $filters['departments'] ?? [];
        $metrics = $filters['metrics'] ?? [];

        $data = [];

        if (in_array('ideas', $metrics)) {
            $data['ideas'] = $this->getIdeasReport($dateRange, $roles, $departments);
        }

        if (in_array('challenges', $metrics)) {
            $data['challenges'] = $this->getChallengesReport($dateRange, $roles, $departments);
        }

        if (in_array('reviews', $metrics)) {
            $data['reviews'] = $this->getReviewsReport($dateRange, $roles, $departments);
        }

        if (in_array('users', $metrics)) {
            $data['users'] = $this->getUsersReport($dateRange, $roles, $departments);
        }

        return [
            'filters' => $filters,
            'date_range' => $dateRange,
            'data' => $data,
            'summary' => $this->generateReportSummary($data),
        ];
    }

    /**
     * Export analytics data to different formats
     */
    public function exportData(array $data, string $format = 'csv'): string
    {
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data);
            case 'excel':
                return $this->exportToExcel($data);
            case 'pdf':
                return $this->exportToPdf($data);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Calculate growth rates
     */
    private function calculateGrowthRates(): array
    {
        $currentMonth = now()->month;
        $previousMonth = now()->subMonth()->month;

        $currentData = [
            'users' => User::whereMonth('created_at', $currentMonth)->count(),
            'ideas' => Idea::whereMonth('created_at', $currentMonth)->count(),
            'challenges' => Challenge::whereMonth('created_at', $currentMonth)->count(),
        ];

        $previousData = [
            'users' => User::whereMonth('created_at', $previousMonth)->count(),
            'ideas' => Idea::whereMonth('created_at', $previousMonth)->count(),
            'challenges' => Challenge::whereMonth('created_at', $previousMonth)->count(),
        ];

        $growthRates = [];
        foreach ($currentData as $key => $current) {
            $previous = $previousData[$key];
            if ($previous > 0) {
                $growthRates[$key] = round((($current - $previous) / $previous) * 100, 2);
            } else {
                $growthRates[$key] = $current > 0 ? 100 : 0;
            }
        }

        return $growthRates;
    }

    /**
     * Calculate average processing time for each stage
     */
    private function calculateAverageProcessingTime(): array
    {
        $stages = ['submitted', 'manager_review', 'sme_review', 'board_review'];
        $processingTimes = [];

        foreach ($stages as $stage) {
            $reviews = Review::where('review_stage', $stage)
                ->whereNotNull('completed_at')
                ->get();

            if ($reviews->count() > 0) {
                $totalTime = $reviews->sum(function ($review) {
                    return $review->completed_at->diffInHours($review->created_at);
                });
                $processingTimes[$stage] = round($totalTime / $reviews->count(), 2);
            } else {
                $processingTimes[$stage] = 0;
            }
        }

        return $processingTimes;
    }

    /**
     * Calculate conversion rates between stages
     */
    private function calculateConversionRates(): array
    {
        $totalSubmitted = Idea::where('current_stage', '!=', 'draft')->count();
        
        return [
            'submitted_to_manager' => $this->getConversionRate('submitted', 'manager_review', $totalSubmitted),
            'manager_to_sme' => $this->getConversionRate('manager_review', 'sme_review', $totalSubmitted),
            'sme_to_board' => $this->getConversionRate('sme_review', 'board_review', $totalSubmitted),
            'board_to_implementation' => $this->getConversionRate('board_review', 'implementation', $totalSubmitted),
        ];
    }

    /**
     * Get conversion rate between two stages
     */
    private function getConversionRate(string $fromStage, string $toStage, int $total): float
    {
        if ($total === 0) return 0;

        $passed = Idea::whereIn('current_stage', [$toStage, 'implementation', 'completed'])->count();
        return round(($passed / $total) * 100, 2);
    }

    /**
     * Get monthly idea submission trends
     */
    private function getMonthlyIdeaTrends(): array
    {
        $trends = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $trends[] = [
                'month' => $date->format('M Y'),
                'submitted' => Idea::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
                'completed' => Idea::where('current_stage', 'completed')
                    ->whereMonth('updated_at', $date->month)
                    ->whereYear('updated_at', $date->year)
                    ->count(),
            ];
        }
        return $trends;
    }

    /**
     * Identify workflow bottlenecks
     */
    private function identifyBottlenecks(): array
    {
        $bottlenecks = [];
        
        // Ideas stuck in each stage for more than average time
        $avgTimes = $this->calculateAverageProcessingTime();
        
        foreach ($avgTimes as $stage => $avgTime) {
            $stuckIdeas = Idea::where('current_stage', $stage)
                ->where('updated_at', '<', now()->subHours($avgTime * 2))
                ->count();
            
            if ($stuckIdeas > 0) {
                $bottlenecks[] = [
                    'stage' => $stage,
                    'stuck_count' => $stuckIdeas,
                    'avg_time_hours' => $avgTime,
                ];
            }
        }

        return $bottlenecks;
    }

    /**
     * Get active users count
     */
    private function getActiveUsers(int $days): int
    {
        return AuditLog::where('created_at', '>=', now()->subDays($days))
            ->distinct('user_id')
            ->count();
    }

    /**
     * Get role distribution
     */
    private function getRoleDistribution(): array
    {
        return User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->pluck('count', 'name')
            ->toArray();
    }

    /**
     * Get engagement trends over time
     */
    private function getEngagementTrends(): array
    {
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trends[] = [
                'date' => $date->format('M j'),
                'logins' => AuditLog::where('action', 'login')
                    ->whereDate('created_at', $date)
                    ->count(),
                'submissions' => Idea::whereDate('created_at', $date)->count(),
                'reviews' => Review::whereDate('created_at', $date)->count(),
            ];
        }
        return $trends;
    }

    /**
     * Get top contributors
     */
    private function getTopContributors(): array
    {
        return User::withCount(['ideas', 'reviews'])
            ->withSum('userPoints', 'points')
            ->orderBy('user_points_sum_points', 'desc')
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'ideas_count' => $user->ideas_count,
                    'reviews_count' => $user->reviews_count,
                    'total_points' => $user->user_points_sum_points ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get collaboration metrics
     */
    private function getCollaborationMetrics(): array
    {
        // This will be enhanced when collaboration features are fully implemented
        return [
            'collaboration_requests' => 0,
            'accepted_collaborations' => 0,
            'collaborative_ideas' => Idea::where('collaboration_enabled', true)->count(),
        ];
    }

    /**
     * Get review performance metrics
     */
    private function getReviewPerformance(): array
    {
        return [
            'avg_review_time' => Review::whereNotNull('completed_at')
                ->get()
                ->avg(function ($review) {
                    return $review->completed_at->diffInHours($review->created_at);
                }),
            'reviews_by_stage' => Review::select('review_stage', DB::raw('count(*) as count'))
                ->groupBy('review_stage')
                ->pluck('count', 'review_stage')
                ->toArray(),
            'reviewer_performance' => $this->getReviewerPerformance(),
        ];
    }

    /**
     * Get reviewer performance
     */
    private function getReviewerPerformance(): array
    {
        return User::join('reviews', 'users.id', '=', 'reviews.reviewer_id')
            ->select('users.name', DB::raw('count(*) as review_count'), 
                    DB::raw('avg(reviews.rating) as avg_rating'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('review_count', 'desc')
            ->take(10)
            ->get()
            ->toArray();
    }

    /**
     * Get department metrics
     */
    private function getDepartmentMetrics(): array
    {
        // This will be enhanced when department data is available
        return [
            'ideas_by_department' => [],
            'performance_by_department' => [],
        ];
    }

    /**
     * Get innovation scores
     */
    private function getInnovationScores(): array
    {
        return [
            'avg_idea_rating' => Review::avg('rating') ?? 0,
            'implementation_rate' => $this->getImplementationRate(),
            'innovation_index' => $this->calculateInnovationIndex(),
        ];
    }

    /**
     * Get implementation rate
     */
    private function getImplementationRate(): float
    {
        $totalIdeas = Idea::where('current_stage', '!=', 'draft')->count();
        $implementedIdeas = Idea::whereIn('current_stage', ['implementation', 'completed'])->count();
        
        return $totalIdeas > 0 ? round(($implementedIdeas / $totalIdeas) * 100, 2) : 0;
    }

    /**
     * Calculate innovation index
     */
    private function calculateInnovationIndex(): float
    {
        // Complex calculation based on multiple factors
        $ideaCount = Idea::count();
        $avgRating = Review::avg('rating') ?? 0;
        $implementationRate = $this->getImplementationRate();
        $userEngagement = $this->getActiveUsers(30);
        
        // Weighted formula for innovation index
        return round(
            ($ideaCount * 0.3) + 
            ($avgRating * 10 * 0.25) + 
            ($implementationRate * 0.25) + 
            ($userEngagement * 0.2), 
            2
        );
    }

    /**
     * Get productivity trends
     */
    private function getProductivityTrends(): array
    {
        $trends = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $trends[] = [
                'month' => $date->format('M Y'),
                'ideas_per_user' => $this->getIdeasPerUser($date),
                'reviews_per_reviewer' => $this->getReviewsPerReviewer($date),
                'avg_processing_time' => $this->getAvgProcessingTimeForMonth($date),
            ];
        }
        return $trends;
    }

    /**
     * Parse date range filter
     */
    private function parseDateRange(string $range): array
    {
        switch ($range) {
            case 'last_7_days':
                return [now()->subDays(7), now()];
            case 'last_30_days':
                return [now()->subDays(30), now()];
            case 'last_3_months':
                return [now()->subMonths(3), now()];
            case 'last_6_months':
                return [now()->subMonths(6), now()];
            case 'last_year':
                return [now()->subYear(), now()];
            default:
                return [now()->subDays(30), now()];
        }
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv(array $data): string
    {
        // Implementation for CSV export
        $filename = 'analytics_export_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);
        
        // Create exports directory if it doesn't exist
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        // Generate CSV content
        $handle = fopen($filepath, 'w');
        
        // Add headers
        fputcsv($handle, ['Metric', 'Value', 'Date']);
        
        // Add data rows
        foreach ($data as $section => $metrics) {
            if (is_array($metrics)) {
                foreach ($metrics as $key => $value) {
                    fputcsv($handle, [$section . '_' . $key, $value, now()->toDateString()]);
                }
            }
        }
        
        fclose($handle);
        
        return $filename;
    }

    /**
     * Get point distribution analytics
     */
    private function getPointDistribution(): array
    {
        return UserPoint::select('reason', DB::raw('sum(points) as total_points'), DB::raw('count(*) as count'))
            ->groupBy('reason')
            ->orderBy('total_points', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get achievement statistics
     */
    private function getAchievementStats(): array
    {
        $achievementService = app(AchievementService::class);
        return $achievementService->getAchievementDistribution();
    }

    /**
     * Get leaderboard trends
     */
    private function getLeaderboardTrends(): array
    {
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trends[] = [
                'date' => $date->format('M j'),
                'top_performer' => User::withSum(['userPoints' => function($query) use ($date) {
                    $query->whereDate('created_at', '<=', $date);
                }], 'points')
                ->orderBy('user_points_sum_points', 'desc')
                ->first()?->name ?? 'N/A',
                'total_points' => UserPoint::whereDate('created_at', $date)->sum('points'),
            ];
        }
        return $trends;
    }

    /**
     * Get engagement correlation with gamification
     */
    private function getEngagementCorrelation(): array
    {
        $users = User::withSum('userPoints', 'points')
            ->withCount(['ideas', 'reviews'])
            ->get();

        $correlation = [];
        foreach ($users as $user) {
            $points = $user->user_points_sum_points ?? 0;
            $activities = $user->ideas_count + $user->reviews_count;
            
            if ($points > 0) {
                $correlation[] = [
                    'points' => $points,
                    'activities' => $activities,
                    'ratio' => $activities > 0 ? round($points / $activities, 2) : 0,
                ];
            }
        }

        return $correlation;
    }

    // Additional helper methods for report generation...
    private function getIdeasPerUser(Carbon $date): float
    {
        $userCount = User::whereDate('created_at', '<=', $date)->count();
        $ideaCount = Idea::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->count();
        
        return $userCount > 0 ? round($ideaCount / $userCount, 2) : 0;
    }

    private function getReviewsPerReviewer(Carbon $date): float
    {
        $reviewerCount = User::whereHas('reviews', function($query) use ($date) {
            $query->whereMonth('created_at', $date->month)
                  ->whereYear('created_at', $date->year);
        })->count();
        
        $reviewCount = Review::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->count();
        
        return $reviewerCount > 0 ? round($reviewCount / $reviewerCount, 2) : 0;
    }

    private function getAvgProcessingTimeForMonth(Carbon $date): float
    {
        return Review::whereMonth('completed_at', $date->month)
            ->whereYear('completed_at', $date->year)
            ->whereNotNull('completed_at')
            ->get()
            ->avg(function ($review) {
                return $review->completed_at->diffInHours($review->created_at);
            }) ?? 0;
    }

    // Placeholder methods for challenge analytics
    private function getChallengeParticipation(): array
    {
        return ['participation_rate' => 0]; // To be implemented with challenge features
    }

    private function getChallengeCompletion(): array
    {
        return ['completion_rate' => 0]; // To be implemented with challenge features
    }

    private function getChallengeTrends(): array
    {
        return []; // To be implemented with challenge features
    }

    private function getChallengeWinnerAnalysis(): array
    {
        return []; // To be implemented with challenge features
    }

    // Placeholder methods for custom reports
    private function getIdeasReport(array $dateRange, array $roles, array $departments): array
    {
        return [
            'total' => Idea::whereBetween('created_at', $dateRange)->count(),
            'by_stage' => Idea::whereBetween('created_at', $dateRange)
                ->select('current_stage', DB::raw('count(*) as count'))
                ->groupBy('current_stage')
                ->pluck('count', 'current_stage')
                ->toArray(),
        ];
    }

    private function getChallengesReport(array $dateRange, array $roles, array $departments): array
    {
        return [
            'total' => Challenge::whereBetween('created_at', $dateRange)->count(),
        ];
    }

    private function getReviewsReport(array $dateRange, array $roles, array $departments): array
    {
        return [
            'total' => Review::whereBetween('created_at', $dateRange)->count(),
            'avg_rating' => Review::whereBetween('created_at', $dateRange)->avg('rating') ?? 0,
        ];
    }

    private function getUsersReport(array $dateRange, array $roles, array $departments): array
    {
        return [
            'new_users' => User::whereBetween('created_at', $dateRange)->count(),
            'active_users' => AuditLog::whereBetween('created_at', $dateRange)
                ->distinct('user_id')
                ->count(),
        ];
    }

    private function generateReportSummary(array $data): array
    {
        $summary = [
            'total_metrics' => count($data),
            'generated_at' => now()->toISOString(),
        ];

        // Add summary statistics based on available data
        if (isset($data['ideas'])) {
            $summary['total_ideas'] = $data['ideas']['total'] ?? 0;
        }

        if (isset($data['users'])) {
            $summary['new_users'] = $data['users']['new_users'] ?? 0;
            $summary['active_users'] = $data['users']['active_users'] ?? 0;
        }

        return $summary;
    }

    // Placeholder for Excel and PDF export
    private function exportToExcel(array $data): string
    {
        // To be implemented with Excel export library
        return 'excel_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
    }

    private function exportToPdf(array $data): string
    {
        // To be implemented with PDF generation library
        return 'pdf_export_' . now()->format('Y_m_d_H_i_s') . '.pdf';
    }
}
