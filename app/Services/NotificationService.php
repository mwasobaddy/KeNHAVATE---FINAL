<?php

namespace App\Services;

use App\Models\User;
use App\Models\AppNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notification types and their email preferences
     */
    protected array $emailEnabledTypes = [
        'status_change',
        'review_assigned', 
        'collaboration_request',
        'deadline_reminder',
        'device_login'
    ];

    /**
     * Send notification to user with multiple delivery channels
     */
    public function sendNotification(User $user, string $type, array $data): AppNotification
    {
        // Create database notification
        $notification = $this->createDatabaseNotification($user, $type, $data);

        // Send email if user preferences allow and type supports email
        if ($this->shouldSendEmail($user, $type)) {
            try {
                $this->sendEmailNotification($user, $notification, $data);
            } catch (\Exception $e) {
                Log::error('Failed to send email notification', [
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Broadcast real-time notification (for future WebSocket implementation)
        $this->broadcastNotification($user, $notification);

        return $notification;
    }

    /**
     * Create database notification record
     */
    protected function createDatabaseNotification(User $user, string $type, array $data): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $data['title'],
            'message' => $data['message'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'metadata' => $this->buildMetadata($data),
            'read_at' => null,
        ]);
    }

    /**
     * Build metadata for notification
     */
    protected function buildMetadata(array $data): array
    {
        $metadata = [];

        // Add actor information if provided
        if (isset($data['actor'])) {
            $metadata['actor'] = $data['actor'];
        }

        // Add stage information for review notifications
        if (isset($data['stage'])) {
            $metadata['stage'] = $data['stage'];
        }

        // Add author information for review assignments
        if (isset($data['author'])) {
            $metadata['author'] = $data['author'];
        }

        // Add any additional metadata
        if (isset($data['metadata'])) {
            $metadata = array_merge($metadata, $data['metadata']);
        }

        return $metadata;
    }

    /**
     * Determine if email should be sent for this notification
     */
    protected function shouldSendEmail(User $user, string $type): bool
    {
        // Check if this notification type supports email
        if (!in_array($type, $this->emailEnabledTypes)) {
            return false;
        }

        // Check user's email preferences (for future implementation)
        // For now, default to true for important notifications
        $emailEnabledByDefault = [
            'status_change',
            'review_assigned',
            'device_login'
        ];

        return in_array($type, $emailEnabledByDefault);
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(User $user, AppNotification $notification, array $data): void
    {
        $emailClass = match($notification->type) {
            'status_change' => \App\Mail\StatusChangeNotification::class,
            'review_assigned' => \App\Mail\ReviewAssignedNotification::class,
            'collaboration_request' => \App\Mail\CollaborationRequestNotification::class,
            'deadline_reminder' => \App\Mail\DeadlineReminderNotification::class,
            'device_login' => \App\Mail\DeviceLoginNotification::class,
            default => \App\Mail\GeneralNotification::class
        };

        try {
            // Check if the email class exists
            if (class_exists($emailClass)) {
                Mail::to($user->email)->send(new $emailClass($notification, $data));
            } else {
                // Use the generic email if specific class doesn't exist
                Mail::to($user->email)->send(new \App\Mail\GeneralNotification($notification, $data));
            }
        } catch (\Exception $e) {
            // Fallback to generic email
            Mail::to($user->email)->send(new \App\Mail\GeneralNotification($notification, $data));
        }
    }

    /**
     * Broadcast real-time notification (placeholder for WebSocket implementation)
     */
    protected function broadcastNotification(User $user, AppNotification $notification): void
    {
        // TODO: Implement real-time broadcasting with Laravel Echo/Pusher
        // For now, we'll just log the broadcast attempt
        Log::info('Broadcasting notification', [
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'type' => $notification->type
        ]);
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotifications(array $users, string $type, array $data): array
    {
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = $this->sendNotification($user, $type, $data);
        }

        return $notifications;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(AppNotification $notification): bool
    {
        return $notification->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get recent notifications for user
     */
    public function getRecentNotifications(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return AppNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete old read notifications (for cleanup)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return AppNotification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Send review deadline reminders
     */
    public function sendReviewDeadlineReminders(): void
    {
        // Get ideas that have been in review stages for more than X days
        $staleReviews = \App\Models\Idea::whereIn('current_stage', [
            'manager_review', 'sme_review', 'board_review'
        ])
        ->where('last_stage_change', '<', now()->subDays(3))
        ->with(['author'])
        ->get();

        foreach ($staleReviews as $idea) {
            // Get appropriate reviewers for the current stage
            $reviewers = $this->getReviewersForStage($idea->current_stage);
            
            foreach ($reviewers as $reviewer) {
                $this->sendNotification($reviewer, 'deadline_reminder', [
                    'title' => 'Review Deadline Reminder',
                    'message' => "The idea '{$idea->title}' has been pending review for more than 3 days.",
                    'related_type' => 'Idea',
                    'related_id' => $idea->id,
                    'author' => $idea->author->name
                ]);
            }
        }
    }

    /**
     * Get reviewers for a specific stage
     */
    protected function getReviewersForStage(string $stage): \Illuminate\Database\Eloquent\Collection
    {
        $roleMap = [
            'manager_review' => 'manager',
            'sme_review' => 'sme',
            'board_review' => 'board_member'
        ];

        $role = $roleMap[$stage] ?? null;
        
        if (!$role) {
            return collect();
        }

        return User::role($role)->get();
    }
}
