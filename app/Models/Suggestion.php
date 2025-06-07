<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suggestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'suggested_changes',
        'rationale',
        'author_id',
        'suggestable_type',
        'suggestable_id',
        'status',
        'priority',
        'implementation_notes',
        'reviewed_by',
        'reviewed_at',
        'implemented_at',
        'upvotes',
        'downvotes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'implemented_at' => 'datetime',
        'upvotes' => 'integer',
        'downvotes' => 'integer',
    ];

    /**
     * Get the suggestable entity (idea, challenge submission, etc.)
     */
    public function suggestable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the suggestion author
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the reviewer (if reviewed)
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get votes for this suggestion
     */
    public function votes(): HasMany
    {
        return $this->hasMany(SuggestionVote::class);
    }

    /**
     * Check if suggestion is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if suggestion is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if suggestion is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if suggestion is implemented
     */
    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    /**
     * Get net vote score
     */
    public function getNetScoreAttribute(): int
    {
        return $this->upvotes - $this->downvotes;
    }

    /**
     * Check if user has voted on this suggestion
     */
    public function getUserVote(User $user): ?string
    {
        $vote = $this->votes()->where('user_id', $user->id)->first();
        return $vote ? $vote->type : null;
    }

    /**
     * Add or update user vote
     */
    public function vote(User $user, string $type): void
    {
        $existingVote = $this->votes()->where('user_id', $user->id)->first();

        if ($existingVote) {
            if ($existingVote->type === $type) {
                // Remove vote if same type
                $existingVote->delete();
                $this->updateVoteCounts();
                return;
            } else {
                // Update vote type
                $existingVote->update(['type' => $type]);
            }
        } else {
            // Create new vote
            $this->votes()->create([
                'user_id' => $user->id,
                'type' => $type,
            ]);
        }

        $this->updateVoteCounts();
    }

    /**
     * Update vote counts
     */
    public function updateVoteCounts(): void
    {
        $this->update([
            'upvotes' => $this->votes()->where('type', 'upvote')->count(),
            'downvotes' => $this->votes()->where('type', 'downvote')->count(),
        ]);
    }

    /**
     * Accept the suggestion
     */
    public function accept(User $reviewer, ?string $implementationNotes = null): void
    {
        $this->update([
            'status' => 'accepted',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'implementation_notes' => $implementationNotes,
        ]);
    }

    /**
     * Reject the suggestion
     */
    public function reject(User $reviewer, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'implementation_notes' => $reason,
        ]);
    }

    /**
     * Mark as implemented
     */
    public function markImplemented(User $implementer): void
    {
        $this->update([
            'status' => 'implemented',
            'implemented_at' => now(),
        ]);
    }

    /**
     * Get suggestions by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get suggestions by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get suggestions ordered by score
     */
    public function scopeByScore($query, string $direction = 'desc')
    {
        return $query->orderBy(\DB::raw('upvotes - downvotes'), $direction);
    }

    /**
     * Get recent suggestions
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
