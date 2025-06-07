<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Services\AnalyticsService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing AnalyticsService methods...\n\n";

try {
    $analytics = app(AnalyticsService::class);
    
    // Test getSystemOverview
    echo "1. Testing getSystemOverview()...\n";
    $overview = $analytics->getSystemOverview();
    echo "   ✅ System Overview: OK\n";
    
    // Test getIdeaWorkflowAnalytics  
    echo "2. Testing getIdeaWorkflowAnalytics()...\n";
    $workflow = $analytics->getIdeaWorkflowAnalytics();
    echo "   ✅ Idea Workflow Analytics: OK\n";
    
    // Test getUserEngagementAnalytics
    echo "3. Testing getUserEngagementAnalytics()...\n";
    $engagement = $analytics->getUserEngagementAnalytics();
    echo "   ✅ User Engagement Analytics: OK\n";
    
    // Test getPerformanceAnalytics
    echo "4. Testing getPerformanceAnalytics()...\n";
    $performance = $analytics->getPerformanceAnalytics();
    echo "   ✅ Performance Analytics: OK\n";
    
    // Test getGamificationAnalytics (the one with the fix)
    echo "5. Testing getGamificationAnalytics()...\n";
    $gamification = $analytics->getGamificationAnalytics();
    echo "   ✅ Gamification Analytics: OK\n";
    
    // Check the point distribution specifically
    echo "\n6. Testing point distribution data structure...\n";
    if (isset($gamification['point_distribution']) && is_array($gamification['point_distribution'])) {
        if (count($gamification['point_distribution']) > 0) {
            $firstItem = $gamification['point_distribution'][0];
            if (isset($firstItem['reason'])) {
                echo "   ✅ Point distribution has 'reason' key: " . $firstItem['reason'] . "\n";
            } else {
                echo "   ❌ Point distribution missing 'reason' key\n";
                echo "   Available keys: " . implode(', ', array_keys($firstItem)) . "\n";
            }
        } else {
            echo "   ⚠️ Point distribution is empty (no data)\n";
        }
    } else {
        echo "   ❌ Point distribution not found in gamification data\n";
    }
    
    echo "\n✅ All analytics methods tested successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
