<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Idea extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'problem_statement',
        'proposed_solution',
        'expected_benefits',
        'implementation_plan',
        'attachments',
        'category_id',
        'business_case',
        'expected_impact',
        'implementation_timeline',
        'resource_requirements',
        'author_id',
        'current_stage',
        'collaboration_enabled',
        'submitted_at',
        'completed_at',
        'last_stage_change',
        'last_reviewer_id',
        'implementation_started_at',
        'manager_review_started_at',
        'manager_review_completed_at',
        'sme_review_started_at',
        'sme_review_completed_at',
        'board_review_started_at',
        'board_review_completed_at',
        'collaboration_started_at',
        'assigned_manager_id',
        'assigned_sme_id',
        'assigned_board_member_id',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'collaboration_enabled' => 'boolean',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_stage_change' => 'datetime',
            'implementation_started_at' => 'datetime',
            'manager_review_started_at' => 'datetime',
            'manager_review_completed_at' => 'datetime',
            'sme_review_started_at' => 'datetime',
            'sme_review_completed_at' => 'datetime',
            'board_review_started_at' => 'datetime',
            'board_review_completed_at' => 'datetime',
            'collaboration_started_at' => 'datetime',
        ];
    }

    /**
     * Author relationship
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Category relationship
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Last reviewer relationship
     */
    public function lastReviewer()
    {
        return $this->belongsTo(User::class, 'last_reviewer_id');
    }

    /**
     * Attachments for this idea
     */
    public function attachments()
    {
        return $this->hasMany(IdeaAttachment::class);
    }

    /**
     * Reviews for this idea
     */
    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Collaborations for this idea
     */
    public function collaborations()
    {
        return $this->morphMany(Collaboration::class, 'collaborable');
    }

    /**
     * Comments for this idea
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Suggestions for this idea
     */
    public function suggestions()
    {
        return $this->morphMany(Suggestion::class, 'suggestable');
    }

    /**
     * Versions for this idea
     */
    public function versions()
    {
        return $this->hasMany(IdeaVersion::class);
    }

    /**
     * Scope for ideas in a specific stage
     */
    public function scopeInStage($query, $stage)
    {
        return $query->where('current_stage', $stage);
    }

    /**
     * Scope for ideas that can be reviewed by a user
     */
    public function scopeReviewableBy($query, User $user)
    {
        return $query->where('author_id', '!=', $user->id);
    }

    /**
     * Check if idea is in draft stage
     */
    public function isDraft(): bool
    {
        return $this->current_stage === 'draft';
    }

    /**
     * Check if idea is read-only (in review stages)
     */
    public function isReadOnly(): bool
    {
        return in_array($this->current_stage, [
            'submitted', 'manager_review', 'sme_review', 
            'board_review', 'implementation', 'completed'
        ]);
    }
}
