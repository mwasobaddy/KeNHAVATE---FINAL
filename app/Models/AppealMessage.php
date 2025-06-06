<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppealMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appeal_type',
        'message',
        'status',
        'admin_response',
        'reviewed_by',
        'reviewed_at',
        'last_sent_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    /**
     * User who sent the appeal
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin who reviewed the appeal
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if user can send a new appeal (one per day limit)
     */
    public static function canSendAppeal(int $userId, string $appealType): bool
    {
        $lastAppeal = static::where('user_id', $userId)
            ->where('appeal_type', $appealType)
            ->where('last_sent_at', '>=', now()->subDay())
            ->exists();

        return !$lastAppeal;
    }

    /**
     * Get the most recent appeal for a user
     */
    public static function getLatestAppeal(int $userId, string $appealType): ?self
    {
        return static::where('user_id', $userId)
            ->where('appeal_type', $appealType)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
