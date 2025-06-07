<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_id',
        'user_id',
        'type',
    ];

    /**
     * Get the suggestion being voted on
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
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
