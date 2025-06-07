<?php

// Test Achievement Stats Structure
echo "Testing Achievement Stats Structure\n";
echo "==================================\n\n";

// Simulate the structure returned by getAchievementDistribution()
$achievementStats = [
    'innovation_pioneer' => [
        'name' => 'Innovation Pioneer',
        'count' => 5,
        'badge' => 'bronze',
        'description' => 'Submit your first 10 ideas',
    ],
    'collaboration_champion' => [
        'name' => 'Collaboration Champion',
        'count' => 2,
        'badge' => 'gold',
        'description' => 'Participate in 50+ active collaborations',
    ],
    'quick_reviewer' => [
        'name' => 'Quick Reviewer',
        'count' => 3,
        'badge' => 'silver',
        'description' => 'Complete 20 reviews within 24 hours',
    ]
];

echo "1. Current Data Structure:\n";
foreach($achievementStats as $key => $data) {
    echo "   Key: $key\n";
    echo "   Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
}

echo "2. Current Template Problem:\n";
echo "   @foreach(\$gamification['achievement_stats'] as \$achievement => \$count)\n";
echo "   This tries to use \$count (which is an array) as a string\n\n";

echo "3. Correct Template Usage:\n";
echo "   @foreach(\$gamification['achievement_stats'] as \$achievement => \$data)\n";
echo "       Achievement: {{ \$data['name'] }}\n";
echo "       Count: {{ \$data['count'] }} users\n";
echo "   @endforeach\n\n";

echo "4. Test Blade Rendering:\n";
foreach($achievementStats as $achievement => $data) {
    echo "   Achievement: {$data['name']}\n";
    echo "   Count: {$data['count']} users\n";
    echo "   Badge: {$data['badge']}\n\n";
}

echo "✅ Fix Required: Update template to use \$data['name'] and \$data['count']\n";
