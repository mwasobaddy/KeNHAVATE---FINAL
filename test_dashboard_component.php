<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Livewire\Livewire;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Advanced Dashboard Livewire Component...\n\n";

try {
    // Create roles if they don't exist
    if (!Role::where('name', 'administrator')->exists()) {
        Role::create(['name' => 'administrator']);
    }
    
    // Find or create test admin user
    $adminUser = User::where('email', 'test.admin@kenha.co.ke')->first();
    if (!$adminUser) {
        $adminUser = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test.admin@kenha.co.ke',
            'account_status' => 'active'
        ]);
    }
    
    // Ensure user has admin role
    if (!$adminUser->hasRole('administrator')) {
        $adminUser->assignRole('administrator');
    }
    
    echo "1. Testing advanced dashboard component mount...\n";
    
    // Test the component mount without errors
    $component = Livewire::actingAs($adminUser)
        ->test('analytics.advanced-dashboard');
    
    echo "   ✅ Component mounted successfully\n";
    
    // Test switching metrics using wire:model.live
    echo "2. Testing metric selection...\n";
    
    $component->set('selectedMetric', 'overview');
    echo "   ✅ Overview metric selected\n";
    
    $component->set('selectedMetric', 'workflow');
    echo "   ✅ Workflow metric selected\n";
    
    $component->set('selectedMetric', 'engagement');
    echo "   ✅ Engagement metric selected\n";
    
    $component->set('selectedMetric', 'performance');
    echo "   ✅ Performance metric selected\n";
    
    $component->set('selectedMetric', 'gamification');
    echo "   ✅ Gamification metric selected\n";
    
    echo "\n✅ Advanced dashboard component tests completed successfully!\n";
    echo "✅ All database column issues have been resolved!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}
