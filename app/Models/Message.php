<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'thread_id',
        'subject',
        'body',
        'related_type',
        'related_id',
        'read_at',
        'reply_to_id',
        'priority',
        'attachments',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Get the sender of the message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the recipient of the message
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Get the message this is a reply to
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get the related entity (Idea, Challenge, etc.)
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark message as unread
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if message is unread
     */
    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    /**
     * Check if message is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->reply_to_id);
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Check if message is high priority
     */
    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }

    /**
     * Check if message is medium priority
     */
    public function isMediumPriority(): bool
    {
        return $this->priority === 'medium';
    }

    /**
     * Check if message is low priority
     */
    public function isLowPriority(): bool
    {
        return $this->priority === 'low';
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCountAttribute(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Get conversation thread messages
     */
    public function getThreadMessages()
    {
        return self::where('thread_id', $this->thread_id)
                  ->orderBy('created_at', 'asc')
                  ->get();
    }

    /**
     * Get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Get read messages
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Get messages by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get high priority messages
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Get messages in a thread
     */
    public function scopeInThread($query, string $threadId)
    {
        return $query->where('thread_id', $threadId);
    }

    /**
     * Get messages sent by user
     */
    public function scopeSentBy($query, int $userId)
    {
        return $query->where('sender_id', $userId);
    }

    /**
     * Get messages received by user
     */
    public function scopeReceivedBy($query, int $userId)
    {
        return $query->where('recipient_id', $userId);
    }

    /**
     * Get messages for user (sent or received)
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->orWhere('recipient_id', $userId);
        });
    }

    /**
     * Get messages with attachments
     */
    public function scopeWithAttachments($query)
    {
        return $query->whereNotNull('attachments')
                    ->where('attachments', '!=', '[]');
    }

    /**
     * Get replies to a specific message
     */
    public function scopeRepliesTo($query, int $messageId)
    {
        return $query->where('reply_to_id', $messageId);
    }

    /**
     * Get recent messages (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Get messages related to ideas
     */
    public function scopeRelatedToIdeas($query)
    {
        return $query->where('related_type', 'App\\Models\\Idea');
    }

    /**
     * Get messages related to challenges
     */
    public function scopeRelatedToChallenges($query)
    {
        return $query->where('related_type', 'App\\Models\\Challenge');
    }
}
