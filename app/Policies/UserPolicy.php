<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or have view_users permission
        return $user->id === $model->id || $user->can('view_users');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('create_users');
    }

    /**
     * Determine whether the user can update the specific user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile or have edit_users permission
        if ($user->id === $model->id) {
            return true;
        }

        // Check if user has edit_users permission
        if (!$user->can('edit_users')) {
            return false;
        }

        // Developers can edit any user
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot edit developer users
        if ($model->hasRole('developer')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the specific user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Check if user has delete_users permission
        if (!$user->can('delete_users')) {
            return false;
        }

        // Developers can delete any user (except themselves)
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot delete developer users
        if ($model->hasRole('developer')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can ban/unban users.
     */
    public function ban(User $user, User $model): bool
    {
        // Users cannot ban themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Check if user has ban_users permission
        if (!$user->can('ban_users')) {
            return false;
        }

        // Developers can ban any user (except themselves)
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot ban developer users
        if ($model->hasRole('developer')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the specific user.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the specific user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only developers can permanently delete users
        return $user->hasRole('developer') && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can change roles of the specific user.
     */
    public function changeRole(User $user, User $model): bool
    {
        // Users cannot change their own roles
        if ($user->id === $model->id) {
            return false;
        }

        // Check if user has edit_users permission
        if (!$user->can('edit_users')) {
            return false;
        }

        // Developers can change any user's role
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot change developer users' roles
        if ($model->hasRole('developer')) {
            return false;
        }

        return true;
    }
}
