<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Services\ChallengeNotificationService;
use App\Services\AuditService;
use Carbon\Carbon;

class ManageChallengeLifecycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'challenges:manage-lifecycle {--dry-run : Show what would be done without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically manage challenge lifecycle transitions (active to review, review to judging, etc.)';

    protected ChallengeNotificationService $challengeNotificationService;
    protected AuditService $auditService;

    public function __construct(ChallengeNotificationService $challengeNotificationService, AuditService $auditService)
    {
        parent::__construct();
        $this->challengeNotificationService = $challengeNotificationService;
        $this->auditService = $auditService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 KeNHAVATE Challenge Lifecycle Management');
        $this->info('==========================================');

        $isDryRun = $this->option('dry-run');
        $startTime = now();
        $processedChallenges = 0;

        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No actual changes will be made');
        }

        try {
            // 1. Transition expired active challenges to review phase
            $this->info('🔍 Checking for expired active challenges...');
            $expiredChallenges = Challenge::where('status', 'active')
                ->where('deadline', '<', now())
                ->get();

            foreach ($expiredChallenges as $challenge) {
                $submissionCount = $challenge->submissions()->count();
                
                $this->info("📋 Challenge: {$challenge->title}");
                $this->info("   - Deadline: {$challenge->deadline}");
                $this->info("   - Submissions: {$submissionCount}");
                
                if ($submissionCount > 0) {
                    if (!$isDryRun) {
                        $oldStatus = $challenge->status;
                        $challenge->update(['status' => 'review']);
                        
                        // Send notifications
                        $this->challengeNotificationService->sendPhaseTransitionNotification(
                            $challenge,
                            $oldStatus,
                            'review'
                        );
                        
                        $this->info("   ✅ Transitioned to review phase");
                    } else {
                        $this->info("   🧪 Would transition to review phase");
                    }
                } else {
                    if (!$isDryRun) {
                        $oldStatus = $challenge->status;
                        $challenge->update(['status' => 'cancelled']);
                        
                        // Send notifications
                        $this->challengeNotificationService->sendPhaseTransitionNotification(
                            $challenge,
                            $oldStatus,
                            'cancelled'
                        );
                        
                        $this->info("   ❌ Cancelled (no submissions)");
                    } else {
                        $this->info("   🧪 Would cancel (no submissions)");
                    }
                }
                
                $processedChallenges++;
            }

            // 2. Auto-transition challenges from review to judging if all reviews completed
            $this->info('🔍 Checking for challenges ready for judging...');
            $reviewChallenges = Challenge::where('status', 'review')->get();

            foreach ($reviewChallenges as $challenge) {
                $submissions = $challenge->submissions()->whereIn('status', ['submitted', 'under_review'])->get();
                $allReviewed = true;

                foreach ($submissions as $submission) {
                    // Check if submission has completed initial reviews
                    $reviewCount = $submission->reviews()->count();
                    if ($reviewCount < 2) { // Assuming minimum 2 reviews (manager + SME)
                        $allReviewed = false;
                        break;
                    }
                }

                if ($allReviewed && $submissions->count() > 0) {
                    $this->info("📋 Challenge ready for judging: {$challenge->title}");
                    
                    if (!$isDryRun) {
                        $oldStatus = $challenge->status;
                        $challenge->update(['status' => 'judging']);
                        
                        // Send notifications
                        $this->challengeNotificationService->sendPhaseTransitionNotification(
                            $challenge,
                            $oldStatus,
                            'judging'
                        );
                        
                        $this->info("   ✅ Transitioned to judging phase");
                    } else {
                        $this->info("   🧪 Would transition to judging phase");
                    }
                    
                    $processedChallenges++;
                }
            }

            // 3. Check for stale challenges in review phase (over 30 days)
            $this->info('🔍 Checking for stale challenges...');
            $staleChallenges = Challenge::whereIn('status', ['review', 'judging'])
                ->where('updated_at', '<', now()->subDays(30))
                ->get();

            foreach ($staleChallenges as $challenge) {
                $this->warn("⚠️  Stale challenge detected: {$challenge->title}");
                $this->warn("   - Status: {$challenge->status}");
                $this->warn("   - Last updated: {$challenge->updated_at->diffForHumans()}");
                
                // Log stale challenge for admin attention
                if (!$isDryRun) {
                    $this->auditService->log(
                        'stale_challenge_detected',
                        'Challenge',
                        $challenge->id,
                        null,
                        [
                            'status' => $challenge->status,
                            'days_stale' => now()->diffInDays($challenge->updated_at),
                            'submissions_count' => $challenge->submissions()->count(),
                        ]
                    );
                }
            }

            // 4. Clean up old draft challenges (over 90 days)
            $this->info('🔍 Checking for old draft challenges...');
            $oldDrafts = Challenge::where('status', 'draft')
                ->where('created_at', '<', now()->subDays(90))
                ->get();

            foreach ($oldDrafts as $draft) {
                $this->info("🗑️  Old draft challenge: {$draft->title}");
                $this->info("   - Created: {$draft->created_at->diffForHumans()}");
                
                if (!$isDryRun) {
                    // Archive old drafts
                    $draft->update(['status' => 'cancelled']);
                    $this->info("   ✅ Archived old draft");
                } else {
                    $this->info("   🧪 Would archive old draft");
                }
                
                $processedChallenges++;
            }

            // Log successful execution
            if (!$isDryRun) {
                $this->auditService->log(
                    'challenge_lifecycle_management',
                    'system',
                    null,
                    null,
                    [
                        'command' => 'challenges:manage-lifecycle',
                        'processed_challenges' => $processedChallenges,
                        'execution_time' => now()->diffInSeconds($startTime),
                        'expired_challenges' => $expiredChallenges->count(),
                        'stale_challenges' => $staleChallenges->count(),
                        'old_drafts' => $oldDrafts->count(),
                    ]
                );
            }

            $this->info("✅ Processed {$processedChallenges} challenges");

        } catch (\Exception $e) {
            $this->error('❌ Error managing challenge lifecycle: ' . $e->getMessage());
            
            if (!$isDryRun) {
                // Log the error
                $this->auditService->log(
                    'challenge_lifecycle_management_failed',
                    'system',
                    null,
                    null,
                    [
                        'command' => 'challenges:manage-lifecycle',
                        'error' => $e->getMessage(),
                        'execution_time' => now()->diffInSeconds($startTime),
                    ]
                );
            }

            return Command::FAILURE;
        }

        $executionTime = now()->diffInSeconds($startTime);
        $this->info("⏱️  Completed in {$executionTime} seconds");

        return Command::SUCCESS;
    }
}
