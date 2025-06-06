<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name', 
        'email',
        'password',
        'gender',
        'phone',
        'phone_number',
        'account_status',
        'terms_accepted',
        'last_login_at',
        'login_count',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted' => 'boolean',
            'login_count' => 'integer',
        ];
    }

    /**
     * Get the user's full name
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Check if user is KeNHA staff
     */
    public function isStaff(): bool
    {
        return Str::endsWith($this->email, '@kenha.co.ke');
    }

    /**
     * Staff profile relationship
     */
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Ideas authored by this user
     */
    public function ideas()
    {
        return $this->hasMany(Idea::class, 'author_id');
    }

    /**
     * Challenges created by this user
     */
    public function challenges()
    {
        return $this->hasMany(Challenge::class, 'created_by');
    }

    /**
     * Challenge submissions by this user
     */
    public function challengeSubmissions()
    {
        return $this->hasMany(ChallengeSubmission::class, 'participant_id');
    }

    /**
     * Reviews conducted by this user
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Collaborations where this user is the collaborator
     */
    public function collaborations()
    {
        return $this->hasMany(Collaboration::class, 'collaborator_id');
    }

    /**
     * Collaboration invitations sent by this user
     */
    public function sentCollaborationInvites()
    {
        return $this->hasMany(Collaboration::class, 'invited_by');
    }

    /**
     * Notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    /**
     * Appeal messages sent by this user
     */
    public function appealMessages()
    {
        return $this->hasMany(AppealMessage::class);
    }

    /**
     * Check if user account is banned
     */
    public function isBanned(): bool
    {
        return $this->account_status === 'banned';
    }

    /**
     * Check if user account is suspended
     */
    public function isSuspended(): bool
    {
        return $this->account_status === 'suspended';
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    /**
     * Points earned by this user
     */
    public function points()
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Total points earned
     */
    public function totalPoints(): int
    {
        return $this->points()->sum('points');
    }

    /**
     * Devices associated with this user
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Messages sent by this user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    /**
     * OTPs generated for this user
     */
    public function otps()
    {
        return $this->hasMany(OTP::class);
    }

    /**
     * Get unread notifications count
     */
    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Get unread messages count
     */
    public function unreadMessagesCount(): int
    {
        return $this->receivedMessages()->unread()->count();
    }

    /**
     * Get trusted devices
     */
    public function trustedDevices()
    {
        return $this->devices()->trusted();
    }

    /**
     * Get current month points
     */
    public function currentMonthPoints(): int
    {
        return $this->points()->currentMonth()->sum('points');
    }

    /**
     * Get current year points
     */
    public function currentYearPoints(): int
    {
        return $this->points()->currentYear()->sum('points');
    }

    /**
     * Check if user can review a specific item (conflict of interest check)
     */
    public function canReview($reviewable): bool
    {
        // Handle different types of reviewable entities
        if (is_null($reviewable)) {
            return false;
        }

        // For Challenge Submissions
        if ($reviewable instanceof \App\Models\ChallengeSubmission) {
            // Cannot review own submissions
            if ($reviewable->participant_id === $this->id) {
                return false;
            }
            
            // Cannot review if user is a team member
            if ($reviewable->team_members && in_array($this->id, $reviewable->team_members)) {
                return false;
            }
            
            return true;
        }

        // For Ideas (existing logic)
        if (method_exists($reviewable, 'author_id') && $reviewable->author_id === $this->id) {
            return false;
        }

        if (method_exists($reviewable, 'created_by') && $reviewable->created_by === $this->id) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has pending reviews
     */
    public function hasPendingReviews(): bool
    {
        return $this->reviews()->pending()->exists();
    }

    /**
     * Get pending reviews count
     */
    public function pendingReviewsCount(): int
    {
        return $this->reviews()->pending()->count();
    }

    /**
     * Get active collaborations
     */
    public function activeCollaborations()
    {
        return $this->collaborations()->active();
    }

    /**
     * Check if user is collaborating on a specific idea
     */
    public function isCollaboratingOn(Idea $idea): bool
    {
        return $this->collaborations()
                   ->where('idea_id', $idea->id)
                   ->whereIn('status', ['accepted', 'active'])
                   ->exists();
    }
}
