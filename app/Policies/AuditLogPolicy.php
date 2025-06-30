<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }
}
