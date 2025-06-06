<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChallengeNotificationService;
use App\Services\AuditService;

class SendChallengeDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'challenges:send-deadline-reminders {--force : Force send reminders regardless of time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send deadline reminder notifications for active challenges';

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
        $this->info('🔔 KeNHAVATE Challenge Deadline Reminders');
        $this->info('==========================================');

        $startTime = now();

        try {
            // Only run during business hours unless forced
            if (!$this->option('force')) {
                $currentHour = now()->hour;
                if ($currentHour < 8 || $currentHour > 17) {
                    $this->warn('⏰ Outside business hours (8 AM - 5 PM). Use --force to override.');
                    return Command::SUCCESS;
                }
            }

            // Send deadline reminders
            $this->info('📤 Sending challenge deadline reminders...');
            $this->challengeNotificationService->sendDeadlineReminders();
            
            $this->info('✅ Deadline reminders sent successfully');

            // Log the command execution
            $this->auditService->log(
                'deadline_reminders_sent',
                'system',
                null,
                null,
                [
                    'command' => 'challenges:send-deadline-reminders',
                    'execution_time' => now()->diffInSeconds($startTime),
                    'forced' => $this->option('force'),
                ]
            );

        } catch (\Exception $e) {
            $this->error('❌ Error sending deadline reminders: ' . $e->getMessage());
            
            // Log the error
            $this->auditService->log(
                'deadline_reminders_failed',
                'system',
                null,
                null,
                [
                    'command' => 'challenges:send-deadline-reminders',
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
