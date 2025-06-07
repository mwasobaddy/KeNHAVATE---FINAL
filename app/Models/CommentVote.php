<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'comment_id',
        'user_id',
        'type',
    ];

    /**
     * Get the comment being voted on
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the user who cast the vote
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is an upvote
     */
    public function isUpvote(): bool
    {
        return $this->type === 'upvote';
    }

    /**
     * Check if this is a downvote
     */
    public function isDownvote(): bool
    {
        return $this->type === 'downvote';
    }
}
