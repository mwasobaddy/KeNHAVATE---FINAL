<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points',
        'action_type',
        'description',
        'related_type',
        'related_id',
        'multiplier',
        'bonus_reason',
    ];

    protected $casts = [
        'points' => 'integer',
        'multiplier' => 'decimal:2',
    ];

    /**
     * Get the user who earned the points
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related entity that generated these points
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if points are positive (earned)
     */
    public function isEarned(): bool
    {
        return $this->points > 0;
    }

    /**
     * Check if points are negative (deducted)
     */
    public function isDeducted(): bool
    {
        return $this->points < 0;
    }

    /**
     * Check if this is a bonus award
     */
    public function isBonus(): bool
    {
        return !is_null($this->bonus_reason);
    }

    /**
     * Get calculated points with multiplier
     */
    public function getCalculatedPointsAttribute(): int
    {
        return (int) ($this->points * ($this->multiplier ?? 1));
    }

    /**
     * Get points by action type
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action_type', $action);
    }

    /**
     * Get earned points (positive)
     */
    public function scopeEarned($query)
    {
        return $query->where('points', '>', 0);
    }

    /**
     * Get deducted points (negative)
     */
    public function scopeDeducted($query)
    {
        return $query->where('points', '<', 0);
    }

    /**
     * Get bonus points
     */
    public function scopeBonus($query)
    {
        return $query->whereNotNull('bonus_reason');
    }

    /**
     * Get points for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get points from idea submissions
     */
    public function scopeFromIdeaSubmission($query)
    {
        return $query->where('action_type', 'idea_submission');
    }

    /**
     * Get points from challenge participation
     */
    public function scopeFromChallengeParticipation($query)
    {
        return $query->where('action_type', 'challenge_participation');
    }

    /**
     * Get points from collaboration
     */
    public function scopeFromCollaboration($query)
    {
        return $query->where('action_type', 'collaboration');
    }

    /**
     * Get points from review completion
     */
    public function scopeFromReviewCompletion($query)
    {
        return $query->where('action_type', 'review_completion');
    }

    /**
     * Get points from winning challenges
     */
    public function scopeFromChallengeWin($query)
    {
        return $query->where('action_type', 'challenge_win');
    }

    /**
     * Get points from idea implementation
     */
    public function scopeFromIdeaImplementation($query)
    {
        return $query->where('action_type', 'idea_implementation');
    }

    /**
     * Get recent points (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Get points for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month);
    }

    /**
     * Get points for current year
     */
    public function scopeCurrentYear($query)
    {
        return $query->whereYear('created_at', now()->year);
    }
}
