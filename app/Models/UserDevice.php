<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_name',
        'device_type',
        'browser',
        'operating_system',
        'ip_address',
        'user_agent',
        'is_trusted',
        'last_used_at',
        'location',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'last_used_at' => 'datetime',
        'location' => 'array',
    ];

    /**
     * Get the user who owns this device
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if device is trusted
     */
    public function isTrusted(): bool
    {
        return $this->is_trusted;
    }

    /**
     * Check if device is untrusted
     */
    public function isUntrusted(): bool
    {
        return !$this->is_trusted;
    }

    /**
     * Mark device as trusted
     */
    public function trust(): void
    {
        $this->update(['is_trusted' => true]);
    }

    /**
     * Mark device as untrusted
     */
    public function untrust(): void
    {
        $this->update(['is_trusted' => false]);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if device was used recently (within 24 hours)
     */
    public function wasUsedRecently(): bool
    {
        return $this->last_used_at && $this->last_used_at->gt(now()->subDay());
    }

    /**
     * Check if device is stale (not used for 30+ days)
     */
    public function isStale(): bool
    {
        return $this->last_used_at && $this->last_used_at->lt(now()->subDays(30));
    }

    /**
     * Get device description for notifications
     */
    public function getDescriptionAttribute(): string
    {
        $parts = [];
        
        if ($this->browser) {
            $parts[] = $this->browser;
        }
        
        if ($this->operating_system) {
            $parts[] = $this->operating_system;
        }
        
        if ($this->device_type) {
            $parts[] = ucfirst($this->device_type);
        }
        
        return implode(' • ', $parts) ?: 'Unknown Device';
    }

    /**
     * Get location string
     */
    public function getLocationStringAttribute(): ?string
    {
        if (!$this->location || !is_array($this->location)) {
            return null;
        }

        $parts = [];
        
        if (isset($this->location['city'])) {
            $parts[] = $this->location['city'];
        }
        
        if (isset($this->location['country'])) {
            $parts[] = $this->location['country'];
        }
        
        return implode(', ', $parts) ?: null;
    }

    /**
     * Get trusted devices
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    /**
     * Get untrusted devices
     */
    public function scopeUntrusted($query)
    {
        return $query->where('is_trusted', false);
    }

    /**
     * Get devices for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get devices used recently
     */
    public function scopeRecentlyUsed($query)
    {
        return $query->where('last_used_at', '>=', now()->subDay());
    }

    /**
     * Get stale devices
     */
    public function scopeStale($query)
    {
        return $query->where('last_used_at', '<', now()->subDays(30));
    }

    /**
     * Get devices by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Get mobile devices
     */
    public function scopeMobile($query)
    {
        return $query->where('device_type', 'mobile');
    }

    /**
     * Get desktop devices
     */
    public function scopeDesktop($query)
    {
        return $query->where('device_type', 'desktop');
    }

    /**
     * Get tablet devices
     */
    public function scopeTablet($query)
    {
        return $query->where('device_type', 'tablet');
    }

    /**
     * Find device by fingerprint
     */
    public function scopeByFingerprint($query, string $fingerprint)
    {
        return $query->where('device_fingerprint', $fingerprint);
    }
}
