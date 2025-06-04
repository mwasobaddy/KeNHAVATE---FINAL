<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Idea;
use App\Services\IdeaWorkflowService;

// Get or create test users
$manager = User::where('email', 'manager@kenha.co.ke')->first();
$sme = User::where('email', 'sme@kenha.co.ke')->first();
$board = User::where('email', 'board@kenha.co.ke')->first();

echo "Found test users:\n";
echo "Manager: " . ($manager ? $manager->email : 'Not found') . "\n";
echo "SME: " . ($sme ? $sme->email : 'Not found') . "\n";
echo "Board: " . ($board ? $board->email : 'Not found') . "\n";

// Check if we have regular users
$regularUsers = User::role('user')->limit(2)->get();
if ($regularUsers->count() < 2) {
    // Create regular users if needed
    for ($i = $regularUsers->count(); $i < 2; $i++) {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User ' . ($i + 1),
            'email' => 'testuser' . ($i + 1) . '@example.com'
        ]);
        $user->assignRole('user');
        $regularUsers->push($user);
        echo "Created user: " . $user->email . "\n";
    }
} else {
    echo "Found existing regular users: " . $regularUsers->pluck('email')->implode(', ') . "\n";
}

// Get or create category
$category = Category::first();
if (!$category) {
    $category = Category::create([
        'name' => 'Infrastructure Innovation',
        'description' => 'Ideas related to road and bridge infrastructure improvements',
        'is_active' => true,
        'display_order' => 1
    ]);
    echo "Created category: " . $category->name . "\n";
}

// Create test ideas if we don't have enough
$existingIdeas = Idea::count();
echo "Existing ideas: $existingIdeas\n";

if ($existingIdeas < 3) {
    $user1 = $regularUsers[0];
    $user2 = $regularUsers[1] ?? $regularUsers[0];
    
    $workflowService = app(IdeaWorkflowService::class);
    
    // Idea 1: Draft stage
    $idea1 = Idea::create([
        'title' => 'Smart Traffic Management System',
        'description' => 'Implement AI-powered traffic management to reduce congestion on major highways. This system would use real-time data analysis to optimize traffic flow and reduce travel times for commuters while improving road safety.',
        'category_id' => $category->id,
        'author_id' => $user1->id,
        'current_stage' => 'draft'
    ]);
    echo "Created draft idea: " . $idea1->title . "\n";

    // Idea 2: Submit for review
    $idea2 = Idea::create([
        'title' => 'Solar-Powered Highway Lighting',
        'description' => 'Install solar-powered LED lighting along rural highways to improve safety and reduce energy costs. This eco-friendly solution would provide sustainable lighting infrastructure while reducing carbon footprint and operational costs.',
        'category_id' => $category->id,
        'author_id' => $user1->id,
        'current_stage' => 'draft'
    ]);
    $workflowService->submitIdea($idea2, $user1);
    echo "Created and submitted idea: " . $idea2->title . "\n";

    // Idea 3: Another user's submission
    $idea3 = Idea::create([
        'title' => 'Digital Toll Collection System',
        'description' => 'Modernize toll collection with digital payment systems, reducing wait times and improving efficiency. Integration with mobile payment platforms and RFID technology would streamline the process and reduce operational costs.',
        'category_id' => $category->id,
        'author_id' => $user2->id,
        'current_stage' => 'draft'
    ]);
    $workflowService->submitIdea($idea3, $user2);
    echo "Created and submitted idea: " . $idea3->title . "\n";
}

// Show final status
echo "\nFinal status:\n";
echo "Total users: " . User::count() . "\n";
echo "Total ideas: " . Idea::count() . "\n";
echo "Ideas in submitted stage: " . Idea::where('current_stage', 'submitted')->count() . "\n";
echo "Ideas in draft stage: " . Idea::where('current_stage', 'draft')->count() . "\n";

echo "\nTest data setup complete!\n";
