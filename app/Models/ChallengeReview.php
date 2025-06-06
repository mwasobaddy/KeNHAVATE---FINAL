<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_submission_id',
        'reviewer_id',
        'stage',
        'overall_score',
        'criteria_scores',
        'feedback',
        'recommendation',
        'strengths_weaknesses',
        'decision',
        'reviewed_at',
        'review_notes',
        'time_spent_minutes',
    ];

    protected $casts = [
        'criteria_scores' => 'array',
        'strengths_weaknesses' => 'array',
        'overall_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'time_spent_minutes' => 'integer',
    ];

    /**
     * Challenge submission being reviewed
     */
    public function challengeSubmission(): BelongsTo
    {
        return $this->belongsTo(ChallengeSubmission::class);
    }

    /**
     * Reviewer who submitted this review
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Check if this is a manager review
     */
    public function isManagerReview(): bool
    {
        return $this->stage === 'manager_review';
    }

    /**
     * Check if this is an SME review
     */
    public function isSmeReview(): bool
    {
        return $this->stage === 'sme_review';
    }

    /**
     * Check if this is a board review
     */
    public function isBoardReview(): bool
    {
        return $this->stage === 'board_review';
    }

    /**
     * Check if the review is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->recommendation, ['approve', 'approved']);
    }

    /**
     * Check if the review is rejected
     */
    public function isRejected(): bool
    {
        return in_array($this->recommendation, ['reject', 'rejected']);
    }

    /**
     * Check if the review needs revision
     */
    public function needsRevision(): bool
    {
        return in_array($this->recommendation, ['needs_revision', 'revise']);
    }

    /**
     * Get the overall recommendation in a consistent format
     */
    public function getRecommendationAttribute($value): string
    {
        // Handle null values
        if ($value === null) {
            return 'pending';
        }
        
        // Normalize recommendations to consistent values
        $normalized = [
            'approve' => 'approve',
            'approved' => 'approve',
            'reject' => 'reject',
            'rejected' => 'reject',
            'needs_revision' => 'needs_revision',
            'revise' => 'needs_revision',
            'pending' => 'pending',
        ];

        return $normalized[$value] ?? $value;
    }

    /**
     * Scope: Get reviews by stage
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope: Get reviews by recommendation
     */
    public function scopeByRecommendation($query, string $recommendation)
    {
        return $query->where('recommendation', $recommendation);
    }

    /**
     * Scope: Get completed reviews
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('reviewed_at');
    }

    /**
     * Scope: Get pending reviews
     */
    public function scopePending($query)
    {
        return $query->whereNull('reviewed_at');
    }

    /**
     * Scope: Get reviews for specific reviewer
     */
    public function scopeByReviewer($query, int $reviewerId)
    {
        return $query->where('reviewer_id', $reviewerId);
    }

    /**
     * Scope: Get reviews with high scores
     */
    public function scopeHighScore($query, float $threshold = 70.0)
    {
        return $query->where('overall_score', '>=', $threshold);
    }

    /**
     * Scope: Get recent reviews (last 30 days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted criteria scores for display
     */
    public function getFormattedCriteriaScoresAttribute(): array
    {
        if (!$this->criteria_scores) {
            return [];
        }

        $formatted = [];
        foreach ($this->criteria_scores as $criterion => $score) {
            $formatted[] = [
                'criterion' => ucwords(str_replace('_', ' ', $criterion)),
                'score' => $score,
                'percentage' => round(($score / 100) * 100, 1) . '%'
            ];
        }

        return $formatted;
    }

    /**
     * Calculate average score from criteria scores
     */
    public function calculateAverageScore(): float
    {
        if (!$this->criteria_scores || empty($this->criteria_scores)) {
            return 0.0;
        }

        $scores = array_values($this->criteria_scores);
        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Get time spent in human readable format
     */
    public function getFormattedTimeSpentAttribute(): string
    {
        if (!$this->time_spent_minutes) {
            return 'Not recorded';
        }

        $hours = floor($this->time_spent_minutes / 60);
        $minutes = $this->time_spent_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Check if review score meets approval threshold
     */
    public function meetsApprovalThreshold(float $threshold = 70.0): bool
    {
        return $this->overall_score >= $threshold;
    }

    /**
     * Get review status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->recommendation) {
            'approve' => 'green',
            'reject' => 'red',
            'needs_revision' => 'yellow',
            'pending' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Boot model events
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate overall score if not provided
        static::saving(function ($model) {
            if (!$model->overall_score && $model->criteria_scores) {
                $model->overall_score = $model->calculateAverageScore();
            }

            // Set reviewed_at timestamp if recommendation is provided and not already set
            if ($model->recommendation && $model->recommendation !== 'pending' && !$model->reviewed_at) {
                $model->reviewed_at = now();
            }
        });
    }
}
