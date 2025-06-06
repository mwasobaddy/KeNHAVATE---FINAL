<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SendChallengeDeadlineReminders::class,
        Commands\SendChallengeDailyDigest::class,
        Commands\ManageChallengeLifecycle::class,
        Commands\TestChallengeSystem::class,
        Commands\TestAuthenticationSystem::class,
        Commands\SetupTestData::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Challenge Deadline Reminders - Run every hour during business hours (8 AM - 5 PM)
        $schedule->command('challenges:send-deadline-reminders')
            ->hourlyAt(0)
            ->between('08:00', '17:00')
            ->weekdays()
            ->description('Send challenge deadline reminder notifications')
            ->onSuccess(function () {
                \Log::info('Challenge deadline reminders sent successfully');
            })
            ->onFailure(function () {
                \Log::error('Challenge deadline reminders failed');
            });

        // Challenge Daily Digest - Run daily at 8:00 AM on weekdays
        $schedule->command('challenges:send-daily-digest')
            ->dailyAt('08:00')
            ->weekdays()
            ->description('Send daily challenge activity digest to managers and administrators')
            ->onSuccess(function () {
                \Log::info('Challenge daily digest sent successfully');
            })
            ->onFailure(function () {
                \Log::error('Challenge daily digest failed');
            });

        // Challenge Lifecycle Management - Run every 6 hours to manage transitions
        $schedule->command('challenges:manage-lifecycle')
            ->everySixHours()
            ->description('Automatically manage challenge lifecycle transitions')
            ->onSuccess(function () {
                \Log::info('Challenge lifecycle management completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Challenge lifecycle management failed');
            });

        // Challenge Lifecycle Management (Dry Run) - Run daily at midnight for monitoring
        $schedule->command('challenges:manage-lifecycle --dry-run')
            ->dailyAt('00:00')
            ->description('Monitor challenge lifecycle transitions (dry run)')
            ->onSuccess(function () {
                \Log::info('Challenge lifecycle monitoring completed');
            });

        // Database maintenance - Clean up old notifications and audit logs
        $schedule->command('model:prune')
            ->daily()
            ->at('02:00')
            ->description('Clean up old model records');

        // Clear expired OTPs
        $schedule->command('auth:clear-expired-otps')
            ->everyThirtyMinutes()
            ->description('Clear expired OTP records');

        // Queue worker monitoring - Restart queue workers daily
        $schedule->command('queue:restart')
            ->dailyAt('03:00')
            ->description('Restart queue workers for fresh memory');

        // Cache optimization - Warm up cache daily
        $schedule->command('cache:clear')
            ->dailyAt('04:00')
            ->description('Clear application cache');

        // Application health check
        $schedule->call(function () {
            \Http::get(config('app.url') . '/up');
        })
            ->everyFifteenMinutes()
            ->description('Application health check');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
