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
        'action',
        'points',
        'description',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'points' => 'integer',
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
     * Get points by action type
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
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
     * Get points for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get points from daily login
     */
    public function scopeFromDailyLogin($query)
    {
        return $query->where('action', 'daily_login');
    }

    /**
     * Get points from account creation
     */
    public function scopeFromAccountCreation($query)
    {
        return $query->where('action', 'account_creation');
    }

    /**
     * Get points from idea submissions
     */
    public function scopeFromIdeaSubmission($query)
    {
        return $query->where('action', 'idea_submission');
    }

    /**
     * Get points from challenge participation
     */
    public function scopeFromChallengeParticipation($query)
    {
        return $query->where('action', 'challenge_participation');
    }

    /**
     * Get points from collaboration
     */
    public function scopeFromCollaboration($query)
    {
        return $query->where('action', 'collaboration_contribution');
    }

    /**
     * Get points from review completion
     */
    public function scopeFromReviewCompletion($query)
    {
        return $query->where('action', 'review_completion');
    }

    /**
     * Get points from idea approval
     */
    public function scopeFromIdeaApproval($query)
    {
        return $query->where('action', 'idea_approved');
    }

    /**
     * Get points from winning challenges
     */
    public function scopeFromChallengeWin($query)
    {
        return $query->where('action', 'challenge_winner');
    }

    /**
     * Get bonus award points
     */
    public function scopeFromBonusAward($query)
    {
        return $query->where('action', 'bonus_award');
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

    /**
     * Get points for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Get points for this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }
}
