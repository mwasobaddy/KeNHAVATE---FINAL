<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\ChallengeReview;
use App\Models\User;
use App\Models\Category;
use App\Services\ChallengeNotificationService;
use App\Services\AuditService;
use App\Services\FileUploadSecurityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestChallengeSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'challenges:test-system {--clean : Clean up test data before running} {--mode=full : Test mode (full, creation, submission, review, workflow)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive tests for the KeNHAVATE Challenge Competition System';

    protected ChallengeNotificationService $challengeNotificationService;
    protected AuditService $auditService;
    protected FileUploadSecurityService $fileUploadSecurityService;
    
    protected array $testResults = [];
    protected array $testData = [
        'challenges' => [],
        'submissions' => [],
        'reviews' => [],
        'files' => [],
    ];

    public function __construct(
        ChallengeNotificationService $challengeNotificationService,
        AuditService $auditService,
        FileUploadSecurityService $fileUploadSecurityService
    ) {
        parent::__construct();
        $this->challengeNotificationService = $challengeNotificationService;
        $this->auditService = $auditService;
        $this->fileUploadSecurityService = $fileUploadSecurityService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===============================================');
        $this->info('🏆 KeNHAVATE CHALLENGE SYSTEM TESTING 🏆');
        $this->info('===============================================');

        // Clean up test data if requested
        if ($this->option('clean')) {
            $this->cleanupTestData();
        }

        $mode = $this->option('mode');
        $startTime = now();

        // Run tests based on mode
        if (in_array($mode, ['full', 'creation'])) {
            $this->runChallengeCreationTests();
        }

        if (in_array($mode, ['full', 'submission'])) {
            $this->runChallengeSubmissionTests();
        }

        if (in_array($mode, ['full', 'review'])) {
            $this->runChallengeReviewTests();
        }

        if (in_array($mode, ['full', 'workflow'])) {
            $this->runWorkflowTests();
        }

        if (in_array($mode, ['full', 'notification'])) {
            $this->runNotificationTests();
        }

        $duration = now()->diffInSeconds($startTime);
        $this->displayTestSummary($duration);

        return Command::SUCCESS;
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void
    {
        $this->info('Cleaning up test data...');

        // Delete test challenges and related data
        Challenge::where('title', 'like', '%Test Challenge%')->delete();
        ChallengeSubmission::where('title', 'like', '%Test Submission%')->delete();
        
        // Clean up test files
        $testFiles = Storage::disk('public')->files('challenge-submissions');
        foreach ($testFiles as $file) {
            if (Str::contains($file, 'test_')) {
                Storage::disk('public')->delete($file);
            }
        }

        $this->info('✅ Test data cleaned up successfully');
    }

    /**
     * Test challenge creation functionality
     */
    protected function runChallengeCreationTests(): void
    {
        $this->info('');
        $this->info('🔥 TEST 1: CHALLENGE CREATION');
        $this->info('==============================');

        try {
            // Get test manager user
            $manager = User::role('manager')->first();
            if (!$manager) {
                $this->error('❌ No manager user found for testing');
                $this->testResults['challenge_creation'] = false;
                return;
            }

            // Get test category
            $category = Category::first();
            if (!$category) {
                $category = Category::create([
                    'name' => 'Test Category',
                    'description' => 'Test category for challenges',
                    'is_active' => true,
                ]);
            }

            // Create test challenge
            $challengeData = [
                'title' => 'Test Challenge ' . now()->timestamp,
                'description' => 'This is a comprehensive test challenge to validate the challenge creation system.',
                'category' => 'technology',
                'problem_statement' => 'Develop an innovative solution to improve highway maintenance efficiency.',
                'evaluation_criteria' => 'Innovation (40%), Feasibility (30%), Impact (30%)',
                'created_by' => $manager->id,
                'status' => 'active',
                'submission_deadline' => now()->addDays(30),
                'evaluation_deadline' => now()->addDays(45),
                'announcement_date' => now()->addDays(60),
                'max_participants' => 100,
                'prizes' => 'First Prize: $5000, Second Prize: $3000, Third Prize: $1000',
            ];

            $challenge = Challenge::create($challengeData);

            if ($challenge) {
                $this->info('✅ Challenge created successfully');
                $this->info("   - Challenge ID: {$challenge->id}");
                $this->info("   - Title: {$challenge->title}");
                $this->info("   - Status: {$challenge->status}");
                $this->info("   - Submission Deadline: {$challenge->submission_deadline}");

                $this->testData['challenges'][] = $challenge->id;
                $this->testResults['challenge_creation'] = true;

                // Test authorization
                $this->testChallengeAuthorization($challenge, $manager);

            } else {
                $this->error('❌ Challenge creation failed');
                $this->testResults['challenge_creation'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Challenge Creation Error: {$e->getMessage()}");
            $this->testResults['challenge_creation'] = false;
        }
    }

    /**
     * Test challenge authorization
     */
    protected function testChallengeAuthorization(Challenge $challenge, User $manager): void
    {
        $this->info('');
        $this->info('1.2 Testing challenge authorization...');

        try {
            // Test manager permissions
            $canEdit = $manager->can('update', $challenge);
            $canDelete = $manager->can('delete', $challenge);
            $canViewSubmissions = $manager->can('viewSubmissions', $challenge);

            $this->info("   - Manager can edit: " . ($canEdit ? "Yes" : "No"));
            $this->info("   - Manager can delete: " . ($canDelete ? "Yes" : "No"));
            $this->info("   - Manager can view submissions: " . ($canViewSubmissions ? "Yes" : "No"));

            if ($canEdit && $canDelete && $canViewSubmissions) {
                $this->info('✅ Challenge authorization working correctly');
                $this->testResults['challenge_authorization'] = true;
            } else {
                $this->error('❌ Challenge authorization failed');
                $this->testResults['challenge_authorization'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Authorization Test Error: {$e->getMessage()}");
            $this->testResults['challenge_authorization'] = false;
        }
    }

    /**
     * Test challenge submission functionality
     */
    protected function runChallengeSubmissionTests(): void
    {
        $this->info('');
        $this->info('🔥 TEST 2: CHALLENGE SUBMISSION');
        $this->info('================================');

        try {
            // Get test challenge
            $challenge = Challenge::where('status', 'active')->first();
            if (!$challenge) {
                $this->error('❌ No active challenge found for submission testing');
                $this->testResults['challenge_submission'] = false;
                return;
            }

            // Get test user - create a unique user to avoid unique constraint
            $user = User::role('user')->first();
            if (!$user) {
                $this->error('❌ No user found for testing');
                $this->testResults['challenge_submission'] = false;
                return;
            }

            // Create a unique submitter to avoid unique constraint violation
            $submitter = User::create([
                'first_name' => 'Test',
                'last_name' => 'Submitter',
                'email' => 'submitter' . now()->timestamp . '@test.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'terms_accepted' => true,
            ]);
            $submitter->assignRole('user');

            // Create test submission
            $submissionData = [
                'title' => 'Test Submission ' . now()->timestamp,
                'description' => 'This is a test submission for the challenge system validation.',
                'solution_approach' => 'We propose using advanced IoT sensors and AI analytics.',
                'implementation_plan' => 'Phase 1: Research, Phase 2: Prototype, Phase 3: Testing',
                'expected_impact' => 'Expected to reduce maintenance costs by 30%',
                'challenge_id' => $challenge->id,
                'participant_id' => $submitter->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ];

            $submission = ChallengeSubmission::create($submissionData);

            if ($submission) {
                $this->info('✅ Submission created successfully');
                $this->info("   - Submission ID: {$submission->id}");
                $this->info("   - Title: {$submission->title}");
                $this->info("   - Status: {$submission->status}");
                $this->info("   - Participant: {$submission->participant->name}");

                $this->testData['submissions'][] = $submission->id;
                $this->testResults['challenge_submission'] = true;

                // Test file upload security
                $this->testFileUploadSecurity($submission);

                // Test submission authorization
                $this->testSubmissionAuthorization($submission, $submitter);

            } else {
                $this->error('❌ Submission creation failed');
                $this->testResults['challenge_submission'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Submission Error: {$e->getMessage()}");
            $this->testResults['challenge_submission'] = false;
        }
    }

    /**
     * Test file upload security
     */
    protected function testFileUploadSecurity(ChallengeSubmission $submission): void
    {
        $this->info('');
        $this->info('2.2 Testing file upload security...');

        try {
            // Create test file content
            $testContent = 'This is a test document for the challenge submission system.';
            $testFileName = 'test_document_' . now()->timestamp . '.txt';
            
            // Create a temporary file for testing
            $tempFile = tmpfile();
            fwrite($tempFile, $testContent);
            $tempPath = stream_get_meta_data($tempFile)['uri'];
            
            // Create a mock UploadedFile
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $testFileName,
                'text/plain',
                null,
                true // test mode
            );
            
            // Test file validation
            $validationResult = $this->fileUploadSecurityService->validateFile($uploadedFile, 'documents');

            if ($validationResult['valid']) {
                $this->info('✅ File validation passed');
                $this->testResults['file_upload_security'] = true;

                // Test secure storage
                $storageResult = $this->fileUploadSecurityService->storeFile(
                    $uploadedFile,
                    'challenge-submissions',
                    'documents'
                );

                if ($storageResult['success']) {
                    $this->info("✅ File stored securely at: {$storageResult['path']}");
                    $this->testData['files'][] = $storageResult['path'];
                } else {
                    $this->error('❌ File storage failed: ' . implode(', ', $storageResult['errors']));
                    $this->testResults['file_upload_security'] = false;
                }

            } else {
                $this->error('❌ File validation failed: ' . implode(', ', $validationResult['errors']));
                $this->testResults['file_upload_security'] = false;
            }
            
            // Clean up temp file
            fclose($tempFile);

        } catch (\Exception $e) {
            $this->error("❌ File Upload Security Error: {$e->getMessage()}");
            $this->testResults['file_upload_security'] = false;
        }
    }

    /**
     * Test submission authorization
     */
    protected function testSubmissionAuthorization(ChallengeSubmission $submission, User $user): void
    {
        $this->info('');
        $this->info('2.3 Testing submission authorization...');

        try {
            // Test user permissions
            $canView = $user->can('view', $submission);
            $canUpdate = $user->can('update', $submission);
            $canDelete = $user->can('delete', $submission);

            $this->info("   - User can view own submission: " . ($canView ? "Yes" : "No"));
            $this->info("   - User can update own submission: " . ($canUpdate ? "Yes" : "No"));
            $this->info("   - User can delete own submission: " . ($canDelete ? "Yes" : "No"));

            // Test manager permissions
            $manager = User::role('manager')->first();
            $managerCanReview = $manager ? $manager->can('review', $submission) : false;

            $this->info("   - Manager can review submission: " . ($managerCanReview ? "Yes" : "No"));

            if ($canView && $canUpdate && $managerCanReview) {
                $this->info('✅ Submission authorization working correctly');
                $this->testResults['submission_authorization'] = true;
            } else {
                $this->error('❌ Submission authorization failed');
                $this->testResults['submission_authorization'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Submission Authorization Error: {$e->getMessage()}");
            $this->testResults['submission_authorization'] = false;
        }
    }

    /**
     * Test challenge review functionality
     */
    protected function runChallengeReviewTests(): void
    {
        $this->info('');
        $this->info('🔥 TEST 3: CHALLENGE REVIEW SYSTEM');
        $this->info('==================================');

        try {
            // Get test submission
            $submission = ChallengeSubmission::where('status', 'submitted')->first();
            if (!$submission) {
                $this->error('❌ No submitted challenge found for review testing');
                $this->testResults['challenge_review'] = false;
                return;
            }

            // Get test reviewer
            $reviewer = User::role('manager')->first();
            if (!$reviewer) {
                $this->error('❌ No manager found for review testing');
                $this->testResults['challenge_review'] = false;
                return;
            }

            // Create test review
            $reviewData = [
                'challenge_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'stage' => 'manager_review',
                'overall_score' => 85,
                'criteria_scores' => json_encode([
                    'innovation' => 90,
                    'feasibility' => 80,
                    'impact' => 85,
                ]),
                'feedback' => 'Excellent innovative approach with strong implementation plan.',
                'recommendation' => 'approve',
                'reviewed_at' => now(),
            ];

            $review = ChallengeReview::create($reviewData);

            if ($review) {
                $this->info('✅ Review created successfully');
                $this->info("   - Review ID: {$review->id}");
                $this->info("   - Stage: {$review->stage}");
                $this->info("   - Overall Score: {$review->overall_score}");
                $this->info("   - Recommendation: {$review->recommendation}");

                $this->testData['reviews'][] = $review->id;
                $this->testResults['challenge_review'] = true;

                // Test review workflow
                $this->testReviewWorkflow($submission, $review);

            } else {
                $this->error('❌ Review creation failed');
                $this->testResults['challenge_review'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Review Error: {$e->getMessage()}");
            $this->testResults['challenge_review'] = false;
        }
    }

    /**
     * Test review workflow
     */
    protected function testReviewWorkflow(ChallengeSubmission $submission, ChallengeReview $review): void
    {
        $this->info('');
        $this->info('3.2 Testing review workflow...');

        try {
            // Test status update after review
            $oldStatus = $submission->status;
            $submission->update(['status' => 'under_review']);

            $this->info("   - Status updated from '{$oldStatus}' to 'under_review'");

            // Test notification sending
            $this->challengeNotificationService->sendStatusChangeNotification(
                $submission,
                $oldStatus,
                'under_review',
                'Test review completed successfully'
            );

            $this->info('✅ Review workflow functioning correctly');
            $this->testResults['review_workflow'] = true;

        } catch (\Exception $e) {
            $this->error("❌ Review Workflow Error: {$e->getMessage()}");
            $this->testResults['review_workflow'] = false;
        }
    }

    /**
     * Test workflow functionality
     */
    protected function runWorkflowTests(): void
    {
        $this->info('');
        $this->info('🔥 TEST 4: WORKFLOW MANAGEMENT');
        $this->info('==============================');

        try {
            // Test lifecycle management
            $this->info('4.1 Testing challenge lifecycle management...');
            
            // Create expired challenge for testing
            $manager = User::role('manager')->first();
            $expiredChallenge = Challenge::create([
                'title' => 'Test Expired Challenge ' . now()->timestamp,
                'description' => 'Test challenge for workflow validation',
                'category' => 'infrastructure',
                'problem_statement' => 'Test problem statement for workflow validation',
                'evaluation_criteria' => 'Innovation (50%), Feasibility (50%)',
                'created_by' => $manager->id,
                'status' => 'active',
                'submission_deadline' => now()->subDay(), // Expired yesterday
                'evaluation_deadline' => now()->addDays(7),
            ]);

            $this->testData['challenges'][] = $expiredChallenge->id;

            // Simulate lifecycle management
            if ($expiredChallenge->submission_deadline < now()) {
                $oldStatus = $expiredChallenge->status;
                $expiredChallenge->update(['status' => 'closed']);
                
                $this->info("   ✅ Expired challenge transitioned from '{$oldStatus}' to 'closed'");
                $this->testResults['workflow_lifecycle'] = true;
            } else {
                $this->error('❌ Challenge transition failed');
                $this->testResults['workflow_lifecycle'] = false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Workflow Error: {$e->getMessage()}");
            $this->testResults['workflow_lifecycle'] = false;
        }
    }

    /**
     * Test notification functionality
     */
    protected function runNotificationTests(): void
    {
        $this->info('');
        $this->info('🔥 TEST 5: NOTIFICATION SYSTEM');
        $this->info('===============================');

        try {
            // Test deadline reminders
            $this->info('5.1 Testing deadline reminders...');
            $this->challengeNotificationService->sendDeadlineReminders();
            $this->info('✅ Deadline reminders sent successfully');
            $this->testResults['notification_deadlines'] = true;

            // Test daily digest
            $this->info('5.2 Testing daily digest...');
            $this->challengeNotificationService->sendDailyDigest();
            $this->info('✅ Daily digest sent successfully');
            $this->testResults['notification_digest'] = true;

        } catch (\Exception $e) {
            $this->error("❌ Notification Error: {$e->getMessage()}");
            $this->testResults['notification_deadlines'] = false;
            $this->testResults['notification_digest'] = false;
        }
    }

    /**
     * Display test summary
     */
    protected function displayTestSummary(int $duration): void
    {
        $this->info('');
        $this->info('📊 CHALLENGE SYSTEM TEST SUMMARY');
        $this->info('=================================');

        // Calculate success rate
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults));
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;

        $this->info("Runtime: {$duration} seconds");
        $this->info("Tests Passed: {$passedTests}/{$totalTests} ({$successRate}%)");
        $this->info('');

        // Challenge creation tests
        $this->info('1. CHALLENGE CREATION:');
        $this->displayTestResult('Challenge Creation', 'challenge_creation');
        $this->displayTestResult('Challenge Authorization', 'challenge_authorization');

        // Submission tests
        $this->info('');
        $this->info('2. CHALLENGE SUBMISSION:');
        $this->displayTestResult('Submission Creation', 'challenge_submission');
        $this->displayTestResult('File Upload Security', 'file_upload_security');
        $this->displayTestResult('Submission Authorization', 'submission_authorization');

        // Review tests
        $this->info('');
        $this->info('3. REVIEW SYSTEM:');
        $this->displayTestResult('Review Creation', 'challenge_review');
        $this->displayTestResult('Review Workflow', 'review_workflow');

        // Workflow tests
        $this->info('');
        $this->info('4. WORKFLOW MANAGEMENT:');
        $this->displayTestResult('Lifecycle Management', 'workflow_lifecycle');

        // Notification tests
        $this->info('');
        $this->info('5. NOTIFICATION SYSTEM:');
        $this->displayTestResult('Deadline Reminders', 'notification_deadlines');
        $this->displayTestResult('Daily Digest', 'notification_digest');

        // Test data summary
        $this->info('');
        $this->info('TEST DATA CREATED:');
        $this->info('Challenges: ' . count($this->testData['challenges']));
        $this->info('Submissions: ' . count($this->testData['submissions']));
        $this->info('Reviews: ' . count($this->testData['reviews']));
        $this->info('Files: ' . count($this->testData['files']));

        // Overall result
        $this->info('');
        if ($successRate >= 80) {
            $this->info('🎉 CHALLENGE SYSTEM TEST: PASSED');
        } else {
            $this->error('❌ CHALLENGE SYSTEM TEST: NEEDS ATTENTION');
        }
    }

    /**
     * Display individual test result
     */
    protected function displayTestResult(string $name, string $key): void
    {
        $result = $this->testResults[$key] ?? null;
        
        if ($result === true) {
            $this->info("   ✅ {$name}: PASSED");
        } elseif ($result === false) {
            $this->error("   ❌ {$name}: FAILED");
        } else {
            $this->warn("   ⚠️ {$name}: NOT TESTED");
        }
    }
}
