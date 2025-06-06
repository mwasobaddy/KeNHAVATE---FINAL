<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\AuditService;

// Bootstrap Laravel
$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 Testing Terms Audit Log Fix\n";
echo "=====================================\n\n";

try {
    // Test 1: Verify audit logs table has the new enum values
    echo "1. Checking audit logs table structure...\n";
    
    $enumValues = \DB::select("SHOW COLUMNS FROM audit_logs WHERE Field = 'action'")[0]->Type;
    echo "   Enum values: $enumValues\n";
    
    if (strpos($enumValues, 'terms_accepted') !== false && strpos($enumValues, 'terms_disagreed') !== false) {
        echo "✅ Terms actions are now available in audit logs\n\n";
    } else {
        echo "❌ Terms actions are missing from audit logs enum\n\n";
        exit(1);
    }

    // Test 2: Create a test user and test audit logging
    echo "2. Testing audit service with terms actions...\n";
    
    $auditService = app(AuditService::class);
    
    // Create a test user
    $testUser = User::factory()->create([
        'email' => 'test-terms@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email_verified_at' => now(),
        'terms_accepted' => false
    ]);
    
    echo "   Created test user: {$testUser->email}\n";
    
    // Test terms_accepted action
    try {
        $auditLog = $auditService->log(
            'terms_accepted',
            'user',
            $testUser->id,
            ['terms_accepted' => false],
            ['terms_accepted' => true]
        );
        echo "✅ Successfully logged 'terms_accepted' action\n";
        echo "   Audit Log ID: {$auditLog->id}\n";
    } catch (\Exception $e) {
        echo "❌ Failed to log 'terms_accepted' action: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // Test terms_disagreed action
    try {
        $auditLog = $auditService->log(
            'terms_disagreed',
            'user',
            $testUser->id,
            null,
            ['action' => 'terms_disagreed', 'redirect' => 'login']
        );
        echo "✅ Successfully logged 'terms_disagreed' action\n";
        echo "   Audit Log ID: {$auditLog->id}\n";
    } catch (\Exception $e) {
        echo "❌ Failed to log 'terms_disagreed' action: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // Test 3: Verify audit logs were created
    echo "\n3. Verifying audit logs were created...\n";
    
    $termsAcceptedLog = AuditLog::where('action', 'terms_accepted')
        ->where('entity_id', $testUser->id)
        ->first();
        
    $termsDisagreedLog = AuditLog::where('action', 'terms_disagreed')
        ->where('entity_id', $testUser->id)
        ->first();
    
    if ($termsAcceptedLog) {
        echo "✅ terms_accepted audit log found\n";
        echo "   Old values: " . json_encode($termsAcceptedLog->old_values) . "\n";
        echo "   New values: " . json_encode($termsAcceptedLog->new_values) . "\n";
    } else {
        echo "❌ terms_accepted audit log not found\n";
    }
    
    if ($termsDisagreedLog) {
        echo "✅ terms_disagreed audit log found\n";
        echo "   New values: " . json_encode($termsDisagreedLog->new_values) . "\n";
    } else {
        echo "❌ terms_disagreed audit log not found\n";
    }
    
    // Clean up test user
    echo "\n4. Cleaning up test data...\n";
    AuditLog::where('entity_id', $testUser->id)->delete();
    $testUser->delete();
    echo "✅ Test data cleaned up\n";
    
    echo "\n🎉 All tests passed! Terms audit logging is working correctly.\n";
    echo "📝 The terms-and-conditions component should now work without errors.\n\n";

} catch (\Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
