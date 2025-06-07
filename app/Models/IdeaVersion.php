<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'idea_id',
        'version_number',
        'title',
        'description',
        'category_id',
        'notes',
        'created_by',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'version_number' => 'integer',
    ];

    /**
     * Get the idea this version belongs to
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * Get the category this version belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created this version
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for current version
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope for ordered versions
     */
    public function scopeOrdered($query, string $direction = 'desc')
    {
        return $query->orderBy('version_number', $direction);
    }

    /**
     * Get version label
     */
    public function getVersionLabelAttribute(): string
    {
        return "v{$this->version_number}";
    }

    /**
     * Check if this is the current version
     */
    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    /**
     * Get differences from another version
     */
    public function getDifferencesFrom(IdeaVersion $otherVersion): array
    {
        $differences = [];

        if ($this->title !== $otherVersion->title) {
            $differences['title'] = [
                'old' => $otherVersion->title,
                'new' => $this->title,
            ];
        }

        if ($this->description !== $otherVersion->description) {
            $differences['description'] = [
                'old' => $otherVersion->description,
                'new' => $this->description,
            ];
        }

        if ($this->category_id !== $otherVersion->category_id) {
            $differences['category'] = [
                'old' => $otherVersion->category->name ?? 'Unknown',
                'new' => $this->category->name ?? 'Unknown',
            ];
        }

        return $differences;
    }
}
