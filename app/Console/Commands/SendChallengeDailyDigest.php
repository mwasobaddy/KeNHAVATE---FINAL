<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChallengeNotificationService;
use App\Services\AuditService;

class SendChallengeDailyDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'challenges:send-daily-digest {--force : Force send digest regardless of time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily digest of challenge activities to managers and administrators';

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
        $this->info('📊 KeNHAVATE Challenge Daily Digest');
        $this->info('===================================');

        $startTime = now();

        try {
            // Only run in the morning unless forced
            if (!$this->option('force')) {
                $currentHour = now()->hour;
                if ($currentHour < 7 || $currentHour > 10) {
                    $this->warn('⏰ Outside optimal digest hours (7 AM - 10 AM). Use --force to override.');
                    return Command::SUCCESS;
                }
            }

            // Send daily digest
            $this->info('📤 Sending daily challenge activity digest...');
            $this->challengeNotificationService->sendDailyDigest();
            
            $this->info('✅ Daily digest sent successfully');

            // Log the command execution
            $this->auditService->log(
                'daily_digest_sent',
                'system',
                null,
                null,
                [
                    'command' => 'challenges:send-daily-digest',
                    'execution_time' => now()->diffInSeconds($startTime),
                    'forced' => $this->option('force'),
                    'date' => now()->subDay()->format('Y-m-d'),
                ]
            );

        } catch (\Exception $e) {
            $this->error('❌ Error sending daily digest: ' . $e->getMessage());
            
            // Log the error
            $this->auditService->log(
                'daily_digest_failed',
                'system',
                null,
                null,
                [
                    'command' => 'challenges:send-daily-digest',
                    'error' => $e->getMessage(),
                    'execution_time' => now()->diffInSeconds($startTime),
                ]
            );

            return Command::FAILURE;
        }

        $executionTime = now()->diffInSeconds($startTime);
        $this->info("⏱️  Completed in {$executionTime} seconds");

        return Command::SUCCESS;
    }
}
