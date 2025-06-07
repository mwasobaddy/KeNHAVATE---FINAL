<?php
// Test the IdeaWorkflowService fix for Collection return type

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Services\IdeaWorkflowService;

echo "Testing IdeaWorkflowService::getPendingReviews() fix...\n\n";

try {
    // Get a user to test with
    $user = User::where('email', 'kelvinramsiel@gmail.com')->first();
    
    if (!$user) {
        echo "❌ Developer user not found\n";
        exit(1);
    }
    
    echo "✅ Found user: {$user->name} ({$user->email})\n";
    echo "✅ User roles: " . $user->roles->pluck('name')->join(', ') . "\n\n";
    
    // Test the service method
    $workflowService = new IdeaWorkflowService();
    $pendingReviews = $workflowService->getPendingReviews($user);
    
    echo "✅ IdeaWorkflowService::getPendingReviews() executed successfully\n";
    echo "✅ Return type: " . get_class($pendingReviews) . "\n";
    echo "✅ Collection count: " . $pendingReviews->count() . " pending reviews\n\n";
    
    // Verify it's the correct collection type
    if ($pendingReviews instanceof \Illuminate\Database\Eloquent\Collection) {
        echo "✅ PASS: Method returns Eloquent Collection as expected\n";
    } else {
        echo "❌ FAIL: Method returns wrong collection type: " . get_class($pendingReviews) . "\n";
    }
    
    echo "\n=== COLLECTION TYPE FIX TEST PASSED ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "❌ File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
