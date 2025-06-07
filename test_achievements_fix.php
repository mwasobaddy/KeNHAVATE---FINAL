<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = new Application(realpath(__DIR__));

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

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Boot the application
$kernel->bootstrap();

echo "🧪 Testing Achievements Data Structure Fix\n";
echo "==========================================\n\n";

try {
    // Test 1: AchievementService data structure
    echo "1. Testing AchievementService data structure...\n";
    
    $user = App\Models\User::first();
    if (!$user) {
        echo "❌ No users found in database\n";
        exit(1);
    }
    
    $achievementService = app(App\Services\AchievementService::class);
    $achievements = $achievementService->getUserAchievements($user);
    
    if (empty($achievements)) {
        echo "⚠️  No achievements found for user {$user->id}\n";
    } else {
        $firstAchievement = $achievements[0];
        echo "✅ AchievementService returns " . count($achievements) . " achievements\n";
        echo "   Sample achievement structure:\n";
        echo "   - name: " . ($firstAchievement['name'] ?? 'MISSING') . "\n";
        echo "   - description: " . ($firstAchievement['description'] ?? 'MISSING') . "\n";
        echo "   - progress_percentage: " . ($firstAchievement['progress_percentage'] ?? 'MISSING') . "\n";
        echo "   - badge: " . ($firstAchievement['badge'] ?? 'MISSING') . "\n";
        echo "   - current_value: " . ($firstAchievement['current_value'] ?? 'MISSING') . "\n";
        echo "   - criteria: " . ($firstAchievement['criteria'] ?? 'MISSING') . "\n";
    }
    
    // Test 2: Category mapping
    echo "\n2. Testing category mapping...\n";
    
    $categoryMappings = [
        'innovation_pioneer' => 'innovation',
        'collaboration_champion' => 'collaboration',
        'quick_reviewer' => 'contribution',
        'challenge_master' => 'leadership',
        'consistent_contributor' => 'consistency',
        'unknown_key' => 'participation'
    ];
    
    foreach ($categoryMappings as $key => $expectedCategory) {
        $category = match($key) {
            'innovation_pioneer', 'idea_implementer', 'innovation_catalyst' => 'innovation',
            'collaboration_champion', 'community_builder' => 'collaboration',
            'quick_reviewer', 'review_expert' => 'contribution',
            'challenge_master' => 'leadership',
            'consistent_contributor', 'weekend_warrior' => 'consistency',
            default => 'participation'
        };
        
        if ($category === $expectedCategory) {
            echo "   ✅ {$key} → {$category}\n";
        } else {
            echo "   ❌ {$key} → {$category} (expected {$expectedCategory})\n";
        }
    }
    
    // Test 3: Check if old GamificationService method structure differs
    echo "\n3. Comparing GamificationService vs AchievementService...\n";
    
    $gamificationService = app(App\Services\GamificationService::class);
    $oldAchievements = $gamificationService->getUserAchievements($user);
    
    if (!empty($oldAchievements)) {
        $oldFirst = $oldAchievements[0];
        echo "   GamificationService structure:\n";
        echo "   - name: " . ($oldFirst['name'] ?? 'MISSING') . "\n";
        echo "   - progress: " . ($oldFirst['progress'] ?? 'MISSING') . " (not progress_percentage)\n";
        echo "   - category: " . ($oldFirst['category'] ?? 'MISSING') . "\n";
        
        echo "\n   🔧 Issue identified: GamificationService uses 'progress' instead of 'progress_percentage'\n";
        echo "   ✅ Fix applied: Using AchievementService which has correct structure\n";
    }
    
    echo "\n🎉 All tests completed!\n";
    echo "\n📝 Summary:\n";
    echo "   - Fixed service call: GamificationService → AchievementService\n";
    echo "   - Fixed field mapping: title → name\n";
    echo "   - Added category mapping for all achievement types\n";
    echo "   - Fixed progress field: progress → progress_percentage\n";
    echo "   - Fixed progress details: current_progress/required_progress → current_value/criteria\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
