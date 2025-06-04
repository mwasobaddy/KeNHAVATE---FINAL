<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OTP extends Model
{
    use HasFactory;

    protected $table = 'otps';

    protected $fillable = [
        'user_id',
        'email',
        'otp_code',
        'purpose',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
        'attempts',
        'max_attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Get the user associated with this OTP
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is still valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed() && !$this->isMaxAttemptsReached();
    }

    /**
     * Check if OTP has been used
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * Check if maximum attempts have been reached
     */
    public function isMaxAttemptsReached(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Mark OTP as used
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Increment attempt count
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttemptsAttribute(): int
    {
        return max(0, $this->max_attempts - $this->attempts);
    }

    /**
     * Get time until expiration in minutes
     */
    public function getMinutesUntilExpirationAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->expires_at);
    }

    /**
     * Verify OTP code against provided code
     */
    public function verify(string $code): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $this->incrementAttempts();

        if ($this->otp_code === $code) {
            $this->markAsUsed();
            return true;
        }

        return false;
    }

    /**
     * Get valid OTPs
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
                    ->whereNull('used_at')
                    ->whereRaw('attempts < max_attempts');
    }

    /**
     * Get expired OTPs
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Get used OTPs
     */
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Get unused OTPs
     */
    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    /**
     * Get OTPs by purpose
     */
    public function scopeByPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * Get login OTPs
     */
    public function scopeLogin($query)
    {
        return $query->where('purpose', 'login');
    }

    /**
     * Get registration OTPs
     */
    public function scopeRegistration($query)
    {
        return $query->where('purpose', 'registration');
    }

    /**
     * Get password reset OTPs
     */
    public function scopePasswordReset($query)
    {
        return $query->where('purpose', 'password_reset');
    }

    /**
     * Get email verification OTPs
     */
    public function scopeEmailVerification($query)
    {
        return $query->where('purpose', 'email_verification');
    }

    /**
     * Get OTPs for a specific email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Get OTPs for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get recent OTPs (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Clean up expired and used OTPs
     */
    public static function cleanup(): int
    {
        return self::where(function ($query) {
            $query->where('expires_at', '<=', now()->subHours(24))
                  ->orWhereNotNull('used_at');
        })->delete();
    }

    /**
     * Generate a new 6-digit OTP code
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
