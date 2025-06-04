<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewable_type',
        'reviewable_id',
        'reviewer_id',
        'review_stage',
        'decision',
        'comments',
        'feedback',
        'criteria_scores',
        'overall_score',
        'completed_at',
        'attachments',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'criteria_scores' => 'array',
        'attachments' => 'array',
        'overall_score' => 'decimal:2',
    ];

    /**
     * Get the reviewable entity (Idea or Challenge)
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the reviewer who submitted this review
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
        return $this->review_stage === 'manager_review';
    }

    /**
     * Check if this is an SME review
     */
    public function isSmeReview(): bool
    {
        return $this->review_stage === 'sme_review';
    }

    /**
     * Check if this is a board review
     */
    public function isBoardReview(): bool
    {
        return $this->review_stage === 'board_review';
    }

    /**
     * Check if the review is approved
     */
    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    /**
     * Check if the review is rejected
     */
    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }

    /**
     * Check if the review needs revision
     */
    public function needsRevision(): bool
    {
        return $this->decision === 'needs_revision';
    }

    /**
     * Get reviews by stage
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('review_stage', $stage);
    }

    /**
     * Get reviews by decision
     */
    public function scopeByDecision($query, string $decision)
    {
        return $query->where('decision', $decision);
    }

    /**
     * Get completed reviews
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Get pending reviews
     */
    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }
}
