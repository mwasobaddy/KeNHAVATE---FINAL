<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Idea;
use App\Models\Category;
use App\Services\IdeaWorkflowService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:setup-data', function () {
    $this->info('Setting up test data for review workflow...');

    // Get or create test user
    $user = User::where('email', 'test@example.com')->first();
    if (!$user) {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password')
        ]);
        $user->assignRole('user');
        $this->info('Created test user: ' . $user->email);
    }

    // Get a category
    $category = Category::first();
    if (!$category) {
        $this->error('No categories found. Please run CategorySeeder first.');
        return 1;
    }

    // Create test idea
    $idea = Idea::create([
        'title' => 'Automated Traffic Management System',
        'description' => 'A comprehensive system to automatically manage traffic flow on major highways using AI and IoT sensors.',
        'category_id' => $category->id,
        'business_case' => 'Reduce traffic congestion by 30% and improve highway safety through intelligent traffic management.',
        'expected_impact' => 'Estimated 25% reduction in travel time and 40% improvement in fuel efficiency.',
        'implementation_timeline' => '18 months for full rollout across major highways',
        'resource_requirements' => 'Initial budget of 500M KES, technical team of 15 engineers, partnership with tech companies',
        'author_id' => $user->id,
        'current_stage' => 'draft',
        'last_stage_change' => now()
    ]);

    $this->info('Created test idea: ' . $idea->title . ' (ID: ' . $idea->id . ')');

    // Submit the idea to start workflow
    try {
        $workflowService = app(IdeaWorkflowService::class);
        $workflowService->submitIdea($idea, $user);
        $this->info('Idea submitted to workflow. Current stage: ' . $idea->fresh()->current_stage);
    } catch (\Exception $e) {
        $this->error('Failed to submit idea: ' . $e->getMessage());
    }

    // Show review users
    $managers = User::role('manager')->get();
    $smes = User::role('sme')->get();
    $boardMembers = User::role('board_member')->get();

    $this->info('Available reviewers:');
    $this->info('Managers: ' . $managers->pluck('email')->implode(', '));
    $this->info('SMEs: ' . $smes->pluck('email')->implode(', '));
    $this->info('Board Members: ' . $boardMembers->pluck('email')->implode(', '));

    $this->info('Test data setup complete! You can now test the review workflow.');
})->purpose('Set up test data for review workflow');
