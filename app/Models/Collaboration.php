<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collaboration extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaborable_type',
        'collaborable_id',
        'collaborator_id',
        'invited_by',
        'status',
        'role',
        'invitation_message',
        'contribution_summary',
        'invited_at',
        'responded_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /**
     * Get the parent collaborable model (idea or challenge submission)
     */
    public function collaborable()
    {
        return $this->morphTo();
    }

    /**
     * Get the idea being collaborated on (if applicable)
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class, 'collaborable_id')
                    ->where('collaborable_type', 'App\\Models\\Idea');
    }

    /**
     * Get the collaborator user
     */
    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collaborator_id');
    }

    /**
     * Get the user who sent the invitation
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if collaboration is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if collaboration is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if collaboration is declined
     */
    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    /**
     * Check if collaboration is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Accept the collaboration invitation
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
            'joined_at' => now(),
        ]);
    }

    /**
     * Decline the collaboration invitation
     */
    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    /**
     * Activate the collaboration (after acceptance)
     */
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
        ]);
    }

    /**
     * Get pending collaborations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get accepted collaborations
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Get active collaborations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get collaborations by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('contribution_type', $type);
    }

    /**
     * Get collaborations for a specific idea
     */
    public function scopeForIdea($query, int $ideaId)
    {
        return $query->where('idea_id', $ideaId);
    }

    /**
     * Get collaborations for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('collaborator_id', $userId);
    }

    /**
     * Get the related Idea model if this collaboration is for an idea
     */
    public function getIdeaModelAttribute()
    {
        return $this->collaborable_type === Idea::class ? $this->collaborable : null;
    }
}
