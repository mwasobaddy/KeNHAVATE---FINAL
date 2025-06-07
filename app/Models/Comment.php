<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * KeNHAVATE Innovation Portal - Comment Model
 * Manages comments for ideas, challenges, and other commentable entities
 */
class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'content',
        'author_id',
        'commentable_type',
        'commentable_id',
        'parent_id',
        'upvotes',
        'downvotes',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'upvotes' => 'integer',
        'downvotes' => 'integer',
    ];

    /**
     * Get the commentable entity (Idea, Challenge, etc.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the comment author
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the parent comment (for replies)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get child comments (replies)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get votes for this comment
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CommentVote::class);
    }

    /**
     * Check if comment is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get net vote score
     */
    public function getNetScoreAttribute(): int
    {
        return $this->upvotes - $this->downvotes;
    }

    /**
     * Check if user has voted on this comment
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
     * Mark comment as edited
     */
    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    /**
     * Get comments for a specific commentable
     */
    public function scopeForCommentable($query, string $type, int $id)
    {
        return $query->where('commentable_type', $type)
                    ->where('commentable_id', $id);
    }

    /**
     * Get top-level comments only
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get recent comments
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}