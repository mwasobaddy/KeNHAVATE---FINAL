<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_type',
        'related_id',
        'read_at',
        'action_url',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related entity (Idea, Challenge, etc.)
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is unread
     */
    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    /**
     * Check if notification is high priority
     */
    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }

    /**
     * Check if notification is medium priority
     */
    public function isMediumPriority(): bool
    {
        return $this->priority === 'medium';
    }

    /**
     * Check if notification is low priority
     */
    public function isLowPriority(): bool
    {
        return $this->priority === 'low';
    }

    /**
     * Get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Get read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Get notifications by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get notifications by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get high priority notifications
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Get recent notifications (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Get notifications for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get status change notifications
     */
    public function scopeStatusChange($query)
    {
        return $query->where('type', 'status_change');
    }

    /**
     * Get review assignment notifications
     */
    public function scopeReviewAssigned($query)
    {
        return $query->where('type', 'review_assigned');
    }

    /**
     * Get collaboration request notifications
     */
    public function scopeCollaborationRequest($query)
    {
        return $query->where('type', 'collaboration_request');
    }

    /**
     * Get deadline reminder notifications
     */
    public function scopeDeadlineReminder($query)
    {
        return $query->where('type', 'deadline_reminder');
    }

    /**
     * Get device login notifications
     */
    public function scopeDeviceLogin($query)
    {
        return $query->where('type', 'device_login');
    }

    /**
     * Get points awarded notifications
     */
    public function scopePointsAwarded($query)
    {
        return $query->where('type', 'points_awarded');
    }
}
