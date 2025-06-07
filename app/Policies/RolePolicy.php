<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_roles');
    }

    /**
     * Determine whether the user can view the specific role.
     */
    public function view(User $user, Role $role): bool
    {
        // Check if user has view_roles permission
        if (!$user->can('view_roles')) {
            return false;
        }

        // Developers can view any role
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot view developer role
        if ($role->name === 'developer') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('create_roles');
    }

    /**
     * Determine whether the user can update the specific role.
     */
    public function update(User $user, Role $role): bool
    {
        // Check if user has edit_roles permission
        if (!$user->can('edit_roles')) {
            return false;
        }

        // Developers can edit any role
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot edit developer role
        if ($role->name === 'developer') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the specific role.
     */
    public function delete(User $user, Role $role): bool
    {
        // Check if user has delete_roles permission
        if (!$user->can('delete_roles')) {
            return false;
        }

        // Cannot delete system roles
        if (in_array($role->name, ['developer', 'administrator', 'user'])) {
            return false;
        }

        // Cannot delete roles that have users assigned
        if ($role->users()->count() > 0) {
            return false;
        }

        // Developers can delete custom roles
        if ($user->hasRole('developer')) {
            return true;
        }

        // Administrators can delete custom roles (non-system roles)
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can restore the specific role.
     */
    public function restore(User $user, Role $role): bool
    {
        return $this->delete($user, $role);
    }

    /**
     * Determine whether the user can permanently delete the specific role.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        // Only developers can permanently delete roles
        return $user->hasRole('developer') && !in_array($role->name, ['developer', 'administrator', 'user']);
    }

    /**
     * Determine whether the user can assign permissions to the specific role.
     */
    public function assignPermissions(User $user, Role $role): bool
    {
        return $this->update($user, $role);
    }

    /**
     * Determine whether the user can assign the specific role to users.
     */
    public function assignToUsers(User $user, Role $role): bool
    {
        // Check if user has edit_users permission
        if (!$user->can('edit_users')) {
            return false;
        }

        // Developers can assign any role
        if ($user->hasRole('developer')) {
            return true;
        }

        // Non-developers cannot assign developer role
        if ($role->name === 'developer') {
            return false;
        }

        return true;
    }
}
