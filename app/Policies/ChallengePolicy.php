<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChallengePolicy
{
    /**
     * KeNHAVATE Innovation Portal - Challenge Authorization Policy
     * 
     * Implements granular permission checking for challenge operations based on:
     * - Role-based access control (8 distinct roles)
     * - Business logic rules and conflict of interest prevention
     * - Challenge lifecycle states and ownership rules
     * - Security requirements and audit compliance
     */

    /**
     * Determine whether the user can view any challenges.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view challenges
        return true;
    }

    /**
     * Determine whether the user can view the challenge.
     */
    public function view(User $user, Challenge $challenge): bool
    {
        // All authenticated users can view individual challenges
        return true;
    }

    /**
     * Determine whether the user can create challenges.
     */
    public function create(User $user): bool
    {
        // Only managers, administrators, and developers can create challenges
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can update the challenge.
     */
    public function update(User $user, Challenge $challenge): bool
    {
        // Developers and administrators can edit any challenge
        if ($user->hasAnyRole(['developer', 'administrator'])) {
            return true;
        }
        
        // Managers can only edit their own challenges
        if ($user->hasRole('manager') && $challenge->created_by === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the challenge.
     */
    public function delete(User $user, Challenge $challenge): bool
    {
        // Cannot delete challenge with existing submissions
        if ($challenge->submissions()->count() > 0) {
            return false;
        }
        
        // Developers and administrators can delete any challenge (if no submissions)
        if ($user->hasAnyRole(['developer', 'administrator'])) {
            return true;
        }
        
        // Managers can only delete their own challenges (if no submissions)
        if ($user->hasRole('manager') && $challenge->created_by === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the challenge.
     */
    public function restore(User $user, Challenge $challenge): bool
    {
        // Only developers and administrators can restore challenges
        return $user->hasAnyRole(['developer', 'administrator']);
    }

    /**
     * Determine whether the user can permanently delete the challenge.
     */
    public function forceDelete(User $user, Challenge $challenge): bool
    {
        // Only developers can permanently delete challenges
        return $user->hasRole('developer');
    }

    /**
     * Determine whether the user can participate in the challenge.
     */
    public function participate(User $user, Challenge $challenge): bool
    {
        // Challenge must be active and accepting submissions
        if ($challenge->status !== 'active') {
            return false;
        }
        
        // Check if deadline has passed
        if ($challenge->deadline && now()->isAfter($challenge->deadline)) {
            return false;
        }
        
        // Users cannot participate in their own challenges
        if ($challenge->created_by === $user->id) {
            return false;
        }
        
        // Check if user already submitted to this challenge
        $existingSubmission = $challenge->submissions()
            ->where('participant_id', $user->id)
            ->exists();
            
        if ($existingSubmission) {
            return false;
        }
        
        // All roles can participate except the challenge author
        return $user->hasAnyRole(['user', 'manager', 'sme', 'challenge_reviewer', 'idea_reviewer', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can view challenge submissions.
     */
    public function viewSubmissions(User $user, Challenge $challenge): bool
    {
        // Challenge author can view submissions
        if ($challenge->created_by === $user->id) {
            return true;
        }
        
        // Authorized roles can view submissions
        return $user->hasAnyRole(['manager', 'challenge_reviewer', 'sme', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can manage challenge winners.
     */
    public function manageWinners(User $user, Challenge $challenge): bool
    {
        // Challenge must have submissions and be in appropriate state
        if ($challenge->submissions()->count() === 0) {
            return false;
        }
        
        // Only specific roles can manage winners
        if ($user->hasAnyRole(['developer', 'administrator'])) {
            return true;
        }
        
        // Managers can manage winners for their own challenges
        if ($user->hasRole('manager') && $challenge->created_by === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can export challenge data.
     */
    public function export(User $user, Challenge $challenge): bool
    {
        // Challenge author can export
        if ($challenge->created_by === $user->id) {
            return true;
        }
        
        // Authorized roles can export
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can view challenge analytics.
     */
    public function viewAnalytics(User $user, Challenge $challenge): bool
    {
        // Challenge author can view analytics
        if ($challenge->created_by === $user->id) {
            return true;
        }
        
        // Authorized roles can view analytics
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Determine whether the user can publish/unpublish the challenge.
     */
    public function publish(User $user, Challenge $challenge): bool
    {
        // Challenge author can publish their own challenge
        if ($challenge->created_by === $user->id && $user->hasRole('manager')) {
            return true;
        }
        
        // Administrators and developers can publish any challenge
        return $user->hasAnyRole(['administrator', 'developer']);
    }

    /**
     * Determine whether the user can clone the challenge.
     */
    public function clone(User $user, Challenge $challenge): bool
    {
        // Users who can create challenges can also clone them
        return $this->create($user);
    }

    /**
     * Determine whether the user can moderate challenge discussions.
     */
    public function moderate(User $user, Challenge $challenge): bool
    {
        // Challenge author can moderate discussions
        if ($challenge->created_by === $user->id) {
            return true;
        }
        
        // Authorized roles can moderate
        return $user->hasAnyRole(['manager', 'administrator', 'developer']);
    }

    /**
     * Business rule: Check if user has conflict of interest
     */
    public function hasConflictOfInterest(User $user, Challenge $challenge): bool
    {
        // Users cannot review or judge their own challenges
        return $challenge->created_by === $user->id;
    }

    /**
     * Determine if challenge is in editable state
     */
    public function isEditable(User $user, Challenge $challenge): bool
    {
        // Check basic update permission first
        if (!$this->update($user, $challenge)) {
            return false;
        }
        
        // Challenges with submissions have limited editability
        if ($challenge->submissions()->count() > 0) {
            // Only specific fields can be edited after submissions exist
            // (e.g., deadline extension, but not criteria changes)
            return $user->hasAnyRole(['administrator', 'developer']);
        }
        
        return true;
    }

    /**
     * Determine if challenge can accept new submissions
     */
    public function acceptsSubmissions(User $user, Challenge $challenge): bool
    {
        return $challenge->status === 'active' && 
               (!$challenge->deadline || now()->isBefore($challenge->deadline));
    }
}
