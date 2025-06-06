<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChallengeSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'challenge_id',
        'participant_id',
        'title',
        'description',
        'solution_approach',
        'implementation_plan',
        'expected_impact',
        'attachments',
        'status',
        'score',
        'ranking',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'score' => 'decimal:2',
            'ranking' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Challenge relationship
     */
    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * Participant relationship
     */
    public function participant()
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    /**
     * Reviews for this submission
     */
    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Challenge-specific reviews for this submission
     */
    public function challengeReviews()
    {
        return $this->hasMany(ChallengeReview::class);
    }

    /**
     * Collaborations for this submission
     */
    public function collaborations()
    {
        return $this->morphMany(Collaboration::class, 'collaborable');
    }

    /**
     * Check if submission is in draft stage
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if submission is read-only
     */
    public function isReadOnly(): bool
    {
        return in_array($this->status, [
            'submitted', 'under_review', 'evaluated', 'winner', 'archived'
        ]);
    }
}
