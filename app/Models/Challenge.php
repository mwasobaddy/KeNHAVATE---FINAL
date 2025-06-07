<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challenge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'problem_statement',
        'evaluation_criteria',
        'prizes',
        'created_by',
        'status',
        'submission_deadline',
        'evaluation_deadline',
        'announcement_date',
        'max_participants',
        'current_participants',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'prizes' => 'array',
            'attachments' => 'array',
            'submission_deadline' => 'datetime',
            'evaluation_deadline' => 'datetime',
            'announcement_date' => 'datetime',
            'max_participants' => 'integer',
            'current_participants' => 'integer',
        ];
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Submissions for this challenge
     */
    public function submissions()
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    /**
     * Scope for active challenges
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('submission_deadline', '>', now());
    }

    /**
     * Check if challenge is open for submissions
     */
    public function isOpen(): bool
    {
        return $this->status === 'active' && 
               $this->submission_deadline > now() &&
               ($this->max_participants === null || 
                $this->current_participants < $this->max_participants);
    }

    /**
     * Check if user can participate
     */
    public function canUserParticipate(User $user): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        // Check if user already submitted
        return !$this->submissions()
                    ->where('participant_id', $user->id)
                    ->exists();
    }
}
