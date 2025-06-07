<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Services\AnalyticsService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing specific AnalyticsService methods for database column issues...\n\n";

try {
    $analytics = app(AnalyticsService::class);
    
    // Test getPerformanceAnalytics specifically
    echo "1. Testing getPerformanceAnalytics()...\n";
    $performance = $analytics->getPerformanceAnalytics();
    echo "   ✅ Performance Analytics: OK\n";
    
    // Check reviewer performance specifically
    if (isset($performance['review_performance']['reviewer_performance'])) {
        $reviewers = $performance['review_performance']['reviewer_performance'];
        echo "   ✅ Reviewer performance data available: " . count($reviewers) . " reviewers\n";
        
        if (count($reviewers) > 0) {
            $firstReviewer = $reviewers[0];
            echo "   ✅ First reviewer keys: " . implode(', ', array_keys($firstReviewer)) . "\n";
            
            if (isset($firstReviewer['avg_rating'])) {
                echo "   ✅ Average rating field present: " . $firstReviewer['avg_rating'] . "\n";
            } else {
                echo "   ❌ Average rating field missing\n";
            }
        } else {
            echo "   ⚠️ No reviewer data available (expected with empty database)\n";
        }
    } else {
        echo "   ⚠️ Reviewer performance not found in performance data\n";
    }
    
    echo "\n✅ Performance analytics test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}
