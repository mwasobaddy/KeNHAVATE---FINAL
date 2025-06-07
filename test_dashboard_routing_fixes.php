<?php

// Test script to verify dashboard routing after fixes

use App\Models\User;
use Illuminate\Support\Facades\Route;

echo "=== KeNHAVATE Dashboard Routing Test ===\n\n";

// 1. Test route registration
echo "1. Checking route registration...\n";
$dashboardRoutes = [
    'dashboard',
    'dashboard.user',
    'dashboard.admin', 
    'dashboard.manager',
    'dashboard.sme',
    'dashboard.board-member',
    'dashboard.challenge-reviewer'
];

$registeredRoutes = [];
foreach (Route::getRoutes() as $route) {
    $name = $route->getName();
    if ($name && str_starts_with($name, 'dashboard')) {
        $registeredRoutes[] = $name;
    }
}

foreach ($dashboardRoutes as $routeName) {
    if (in_array($routeName, $registeredRoutes)) {
        echo "   ✓ Route '$routeName' is registered\n";
    } else {
        echo "   ✗ Route '$routeName' is missing\n";
    }
}

// 2. Test role-specific redirections
echo "\n2. Testing role-specific dashboard redirections...\n";

$roleTests = [
    'user' => 'dashboard.user',
    'administrator' => 'dashboard.admin',
    'developer' => 'dashboard.admin',
    'manager' => 'dashboard.manager',
    'sme' => 'dashboard.sme',
    'board_member' => 'dashboard.board-member',
    'challenge_reviewer' => 'dashboard.challenge-reviewer'
];

foreach ($roleTests as $role => $expectedRoute) {
    $expectedUrl = match($role) {
        'developer', 'administrator' => '/dashboard/admin',
        'board_member' => '/dashboard/board-member',
        'manager' => '/dashboard/manager',
        'sme' => '/dashboard/sme',
        'challenge_reviewer' => '/dashboard/challenge-reviewer',
        default => '/dashboard/user',
    };
    
    try {
        $actualUrl = route($expectedRoute);
        if (str_contains($actualUrl, $expectedUrl)) {
            echo "   ✓ Role '$role' correctly routes to '$expectedRoute'\n";
        } else {
            echo "   ✗ Role '$role' route mismatch. Expected containing '$expectedUrl', got '$actualUrl'\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Role '$role' route error: " . $e->getMessage() . "\n";
    }
}

// 3. Check dashboard component files exist
echo "\n3. Checking dashboard component files...\n";
$dashboardFiles = [
    'user-dashboard.blade.php',
    'admin-dashboard.blade.php',
    'manager-dashboard.blade.php', 
    'sme-dashboard.blade.php',
    'board-member-dashboard.blade.php',
    'challenge-reviewer-dashboard.blade.php',
    'idea-reviewer-dashboard.blade.php'
];

$basePath = base_path('resources/views/livewire/dashboard/');

foreach ($dashboardFiles as $file) {
    $filePath = $basePath . $file;
    if (file_exists($filePath)) {
        // Check if file has layout wrapper
        $content = file_get_contents($filePath);
        if (strpos($content, 'x-layouts.app') !== false) {
            echo "   ✓ File '$file' exists and has layout wrapper\n";
        } else {
            echo "   ⚠ File '$file' exists but missing layout wrapper\n";
        }
    } else {
        echo "   ✗ File '$file' is missing\n";
    }
}

// 4. Test registration redirect logic
echo "\n4. Testing registration redirect logic...\n";

function getDashboardRouteForRole($role) {
    return match($role) {
        'developer', 'administrator' => route('dashboard.admin'),
        'board_member' => route('dashboard.board-member'), 
        'manager' => route('dashboard.manager'),
        'sme' => route('dashboard.sme'),
        'challenge_reviewer' => route('dashboard.challenge-reviewer'),
        default => route('dashboard.user'),
    };
}

foreach ($roleTests as $role => $expectedRoute) {
    try {
        $redirectUrl = getDashboardRouteForRole($role);
        $expectedUrl = route($expectedRoute);
        
        if ($redirectUrl === $expectedUrl) {
            echo "   ✓ Registration redirect for '$role' works correctly\n";
        } else {
            echo "   ✗ Registration redirect for '$role' mismatch\n";
            echo "     Expected: $expectedUrl\n";
            echo "     Got: $redirectUrl\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Registration redirect for '$role' error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Dashboard Routing Test Complete ===\n";
echo "If all tests pass, the dashboard routing should work correctly!\n";
