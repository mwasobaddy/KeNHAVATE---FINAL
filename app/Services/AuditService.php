<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * KeNHAVATE Innovation Portal - Audit Service
 * Comprehensive audit logging for all user actions and system events
 * Captures: user actions, entity changes, security events, system operations
 */
class AuditService
{
    /**
     * Log a user action with complete context
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Log account creation with additional context
     */
    public function logAccountCreation(int $userId, array $userData): AuditLog
    {
        return $this->log(
            'account_creation',
            'user',
            $userId,
            null,
            [
                'email' => $userData['email'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'account_type' => str_contains($userData['email'], '@kenha.co.ke') ? 'staff' : 'public'
            ]
        );
    }

    /**
     * Log login attempts and successes
     */
    public function logLogin(int $userId, bool $successful = true, ?string $reason = null): AuditLog
    {
        return $this->log(
            $successful ? 'login_success' : 'login_failed',
            'user',
            $userId,
            null,
            [
                'successful' => $successful,
                'reason' => $reason,
                'session_id' => session()->getId()
            ]
        );
    }

    /**
     * Log idea submissions and updates
     */
    public function logIdeaAction(string $action, int $ideaId, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return $this->log($action, 'idea', $ideaId, $oldValues, $newValues);
    }

    /**
     * Log challenge related actions
     */
    public function logChallengeAction(string $action, int $challengeId, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return $this->log($action, 'challenge', $challengeId, $oldValues, $newValues);
    }

    /**
     * Log review submissions and status changes
     */
    public function logReviewAction(string $action, int $reviewId, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return $this->log($action, 'review', $reviewId, $oldValues, $newValues);
    }

    /**
     * Log collaboration invitations and responses
     */
    public function logCollaborationAction(string $action, int $collaborationId, ?array $context = null): AuditLog
    {
        return $this->log($action, 'collaboration', $collaborationId, null, $context);
    }

    /**
     * Log account status changes (banning, reporting, etc.)
     */
    public function logAccountStatusChange(int $targetUserId, string $oldStatus, string $newStatus, ?string $reason = null): AuditLog
    {
        return $this->log(
            'account_status_change',
            'user',
            $targetUserId,
            ['account_status' => $oldStatus],
            [
                'account_status' => $newStatus,
                'reason' => $reason,
                'changed_by' => Auth::id()
            ]
        );
    }

    /**
     * Log device login and trust events
     */
    public function logDeviceActivity(string $action, ?int $deviceId = null, ?array $deviceInfo = null): AuditLog
    {
        return $this->log(
            $action,
            'device',
            $deviceId,
            null,
            $deviceInfo ?? [
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip()
            ]
        );
    }

    /**
     * Get audit trail for a specific entity
     */
    public function getEntityAuditTrail(string $entityType, int $entityId, int $limit = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuditLog::with('user')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get user activity history
     */
    public function getUserActivityHistory(int $userId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get security-related audit logs
     */
    public function getSecurityLogs(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        $securityActions = [
            'login_failed',
            'account_creation',
            'account_status_change',
            'device_login_new',
            'otp_validation_failed',
            'password_reset'
        ];

        return AuditLog::whereIn('action', $securityActions)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get audit statistics for dashboard
     */
    public function getAuditStats(): array
    {
        $today = today();
        
        return [
            'total_actions_today' => AuditLog::whereDate('created_at', $today)->count(),
            'unique_users_today' => AuditLog::whereDate('created_at', $today)
                ->distinct('user_id')->count('user_id'),
            'failed_logins_today' => AuditLog::whereDate('created_at', $today)
                ->where('action', 'login_failed')->count(),
            'idea_submissions_today' => AuditLog::whereDate('created_at', $today)
                ->where('action', 'idea_submission')->count(),
            'most_active_users' => $this->getMostActiveUsers(),
            'top_actions' => $this->getTopActions()
        ];
    }

    /**
     * Get most active users in the last 7 days
     */
    protected function getMostActiveUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::with('user')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, COUNT(*) as action_count')
            ->groupBy('user_id')
            ->orderBy('action_count', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Get most frequent actions in the last 7 days
     */
    protected function getTopActions(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Search audit logs with filters
     */
    public function searchLogs(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AuditLog::with('user');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', 'LIKE', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 25);
    }
}
