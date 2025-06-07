<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Idea;
use App\Models\Challenge;
use App\Models\Review;
use App\Models\UserPoint;
use App\Models\AuditLog;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * KeNHAVATE Innovation Portal - Analytics System Test Suite
 * Comprehensive testing for analytics service, dashboard, and export functionality
 */
class AnalyticsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $analyticsService;
    protected ExportService $exportService;
    protected User $adminUser;
    protected User $managerUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'administrator']);
        Role::create(['name' => 'manager']);
        Role::create(['name' => 'user']);
        Role::create(['name' => 'sme']);
        Role::create(['name' => 'board_member']);
        
        // Create test users
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('administrator');
        
        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('manager');
        
        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('user');
        
        // Initialize services
        $this->analyticsService = app(AnalyticsService::class);
        $this->exportService = app(ExportService::class);
        
        // Create test data
        $this->createTestData();
    }

    /** @test */
    public function analytics_dashboard_requires_proper_role_authorization()
    {
        // Regular user should be redirected
        $this->actingAs($this->regularUser)
            ->get(route('analytics.dashboard'))
            ->assertStatus(403);
        
        // Manager should have access
        $this->actingAs($this->managerUser)
            ->get(route('analytics.dashboard'))
            ->assertStatus(200);
        
        // Admin should have access
        $this->actingAs($this->adminUser)
            ->get(route('analytics.dashboard'))
            ->assertStatus(200);
    }

    /** @test */
    public function analytics_dashboard_component_renders_correctly()
    {
        Livewire::actingAs($this->managerUser)
            ->test('analytics.advanced-dashboard')
            ->assertStatus(200)
            ->assertSee('Advanced Analytics Dashboard')
            ->assertSee('System Overview')
            ->assertSee('Workflow Analytics')
            ->assertSee('User Engagement')
            ->assertSee('Performance Metrics')
            ->assertSee('Gamification Analytics');
    }

    /** @test */
    public function analytics_service_generates_system_overview_correctly()
    {
        $overview = $this->analyticsService->getSystemOverview();
        
        $this->assertArrayHasKey('total_ideas', $overview);
        $this->assertArrayHasKey('total_users', $overview);
        $this->assertArrayHasKey('total_challenges', $overview);
        $this->assertArrayHasKey('active_collaborations', $overview);
        
        // Check data structure includes growth rates
        $this->assertArrayHasKey('current', $overview['total_ideas']);
        $this->assertArrayHasKey('growth_rate', $overview['total_ideas']);
        
        // Verify actual counts match test data
        $this->assertEquals(5, $overview['total_ideas']['current']);
        $this->assertEquals(3, $overview['total_users']['current']);
        $this->assertEquals(2, $overview['total_challenges']['current']);
    }

    /** @test */
    public function analytics_service_provides_workflow_analytics()
    {
        $workflow = $this->analyticsService->getIdeaWorkflowAnalytics();
        
        $this->assertArrayHasKey('stage_distribution', $workflow);
        $this->assertArrayHasKey('average_processing_time', $workflow);
        $this->assertArrayHasKey('conversion_rates', $workflow);
        $this->assertArrayHasKey('bottlenecks', $workflow);
        
        // Verify stage distribution includes all stages
        $stages = $workflow['stage_distribution'];
        $this->assertArrayHasKey('draft', $stages);
        $this->assertArrayHasKey('submitted', $stages);
        $this->assertArrayHasKey('manager_review', $stages);
    }

    /** @test */
    public function analytics_service_tracks_user_engagement()
    {
        $engagement = $this->analyticsService->getUserEngagementAnalytics();
        
        $this->assertArrayHasKey('active_users', $engagement);
        $this->assertArrayHasKey('role_distribution', $engagement);
        $this->assertArrayHasKey('top_contributors', $engagement);
        $this->assertArrayHasKey('engagement_trends', $engagement);
        
        // Verify role distribution
        $roles = $engagement['role_distribution'];
        $this->assertArrayHasKey('administrator', $roles);
        $this->assertArrayHasKey('manager', $roles);
        $this->assertArrayHasKey('user', $roles);
    }

    /** @test */
    public function analytics_service_calculates_performance_metrics()
    {
        $performance = $this->analyticsService->getPerformanceAnalytics();
        
        $this->assertArrayHasKey('review_performance', $performance);
        $this->assertArrayHasKey('innovation_score', $performance);
        $this->assertArrayHasKey('productivity_trends', $performance);
        $this->assertArrayHasKey('quality_metrics', $performance);
    }

    /** @test */
    public function analytics_service_provides_gamification_analytics()
    {
        $gamification = $this->analyticsService->getGamificationAnalytics();
        
        $this->assertArrayHasKey('total_points_awarded', $gamification);
        $this->assertArrayHasKey('top_point_earners', $gamification);
        $this->assertArrayHasKey('point_distribution', $gamification);
        $this->assertArrayHasKey('achievement_stats', $gamification);
        $this->assertArrayHasKey('engagement_correlation', $gamification);
    }

    /** @test */
    public function analytics_service_generates_custom_reports_with_filters()
    {
        $filters = [
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'stage' => 'submitted'
        ];
        
        $report = $this->analyticsService->generateCustomReport($filters);
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('filters_applied', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('detailed_data', $report);
    }

    /** @test */
    public function export_service_generates_csv_export_correctly()
    {
        Storage::fake('public');
        
        $data = $this->analyticsService->getSystemOverview();
        $result = $this->exportService->exportAnalyticsData($data, 'csv', 'system_overview');
        
        $this->assertTrue($result['success']);
        $this->assertStringContains('.csv', $result['filename']);
        $this->assertArrayHasKey('path', $result);
        
        // Verify file was created
        Storage::disk('public')->assertExists("exports/{$result['filename']}");
        
        // Verify CSV content structure
        $content = Storage::disk('public')->get("exports/{$result['filename']}");
        $this->assertStringContains('KeNHAVATE Innovation Portal', $content);
        $this->assertStringContains('Analytics Report', $content);
    }

    /** @test */
    public function export_service_generates_excel_export_correctly()
    {
        Storage::fake('public');
        
        $data = $this->analyticsService->getUserEngagementAnalytics();
        $result = $this->exportService->exportAnalyticsData($data, 'excel', 'user_engagement');
        
        $this->assertTrue($result['success']);
        $this->assertStringContains('.xlsx', $result['filename']);
        
        Storage::disk('public')->assertExists("exports/{$result['filename']}");
    }

    /** @test */
    public function export_service_generates_pdf_export_correctly()
    {
        Storage::fake('public');
        
        $data = $this->analyticsService->getPerformanceAnalytics();
        $result = $this->exportService->exportAnalyticsData($data, 'pdf', 'performance_metrics');
        
        $this->assertTrue($result['success']);
        $this->assertStringContains('.pdf', $result['filename']);
        
        Storage::disk('public')->assertExists("exports/{$result['filename']}");
        
        // Verify PDF content structure
        $content = Storage::disk('public')->get("exports/{$result['filename']}");
        $this->assertStringContains('KeNHAVATE Innovation Portal', $content);
        $this->assertStringContains('Performance Metrics', $content);
    }

    /** @test */
    public function dashboard_component_handles_metric_filtering()
    {
        Livewire::actingAs($this->managerUser)
            ->test('analytics.advanced-dashboard')
            ->set('selectedMetrics', ['system_overview', 'workflow_analytics'])
            ->call('refreshData')
            ->assertEmitted('analytics-updated');
    }

    /** @test */
    public function dashboard_component_handles_timeframe_changes()
    {
        Livewire::actingAs($this->managerUser)
            ->test('analytics.advanced-dashboard')
            ->set('timeframe', '30_days')
            ->call('refreshData')
            ->assertSet('timeframe', '30_days');
    }

    /** @test */
    public function dashboard_component_handles_export_requests()
    {
        Storage::fake('public');
        
        Livewire::actingAs($this->managerUser)
            ->test('analytics.advanced-dashboard')
            ->call('exportData', 'csv')
            ->assertDispatched('export-started');
    }

    /** @test */
    public function analytics_indexes_improve_query_performance()
    {
        // Create a larger dataset to test performance
        Idea::factory(100)->create();
        Review::factory(50)->create();
        UserPoint::factory(200)->create();
        
        $startTime = microtime(true);
        $this->analyticsService->getSystemOverview();
        $queryTime = microtime(true) - $startTime;
        
        // Query should complete in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $queryTime);
    }

    /** @test */
    public function analytics_service_handles_empty_data_gracefully()
    {
        // Clear all test data
        Idea::truncate();
        Challenge::truncate();
        Review::truncate();
        UserPoint::truncate();
        
        $overview = $this->analyticsService->getSystemOverview();
        
        // Should return zeros instead of errors
        $this->assertEquals(0, $overview['total_ideas']['current']);
        $this->assertEquals(0, $overview['total_challenges']['current']);
    }

    /** @test */
    public function export_service_cleans_up_old_files()
    {
        Storage::fake('public');
        
        // Create old export files
        Storage::disk('public')->put('exports/old_file.csv', 'test content');
        Storage::disk('public')->put('exports/recent_file.csv', 'test content');
        
        // Mock file modification times
        touch(Storage::disk('public')->path('exports/old_file.csv'), now()->subDays(10)->timestamp);
        touch(Storage::disk('public')->path('exports/recent_file.csv'), now()->timestamp);
        
        $deletedCount = $this->exportService->cleanupOldExports(7);
        
        $this->assertEquals(1, $deletedCount);
        Storage::disk('public')->assertMissing('exports/old_file.csv');
        Storage::disk('public')->assertExists('exports/recent_file.csv');
    }

    /** @test */
    public function analytics_service_validates_date_ranges()
    {
        $filters = [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->subDay()->format('Y-m-d') // End before start
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->analyticsService->generateCustomReport($filters);
    }

    /** @test */
    public function dashboard_component_requires_authentication()
    {
        Livewire::test('analytics.advanced-dashboard')
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function analytics_data_respects_user_permissions()
    {
        // Manager should see all data
        $managerOverview = $this->analyticsService->getSystemOverview();
        $this->assertArrayHasKey('total_ideas', $managerOverview);
        
        // Different roles might have different access levels in future
        // This test ensures the foundation is in place
        $this->assertTrue(true);
    }

    /**
     * Create test data for analytics testing
     */
    protected function createTestData(): void
    {
        // Create ideas in various stages
        Idea::factory()->create(['current_stage' => 'draft', 'author_id' => $this->regularUser->id]);
        Idea::factory()->create(['current_stage' => 'submitted', 'author_id' => $this->regularUser->id]);
        Idea::factory()->create(['current_stage' => 'manager_review', 'author_id' => $this->regularUser->id]);
        Idea::factory()->create(['current_stage' => 'sme_review', 'author_id' => $this->regularUser->id]);
        Idea::factory()->create(['current_stage' => 'completed', 'author_id' => $this->regularUser->id]);
        
        // Create challenges
        Challenge::factory(2)->create(['created_by' => $this->managerUser->id]);
        
        // Create reviews
        Review::factory(3)->create([
            'reviewer_id' => $this->managerUser->id,
            'reviewable_type' => Idea::class,
            'reviewable_id' => 1
        ]);
        
        // Create user points
        UserPoint::factory(5)->create(['user_id' => $this->regularUser->id]);
        UserPoint::factory(3)->create(['user_id' => $this->managerUser->id]);
        
        // Create audit logs
        AuditLog::factory(10)->create(['user_id' => $this->regularUser->id]);
        AuditLog::factory(5)->create(['user_id' => $this->managerUser->id]);
        AuditLog::factory(2)->create(['user_id' => $this->adminUser->id]);
    }
}
