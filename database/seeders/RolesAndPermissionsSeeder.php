<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * KeNHAVATE Innovation Portal - Role and Permission Seeder
     * 
     * Creates 7 distinct roles with comprehensive permission matrix:
     * 1. Developer - System administration
     * 2. Administrator - User management, full oversight
     * 3. Board Member - Final approval authority
     * 4. Manager - First-stage reviews, challenge creation
     * 5. Subject Matter Expert (SME) - Technical evaluation
     * 6. Challenge Reviewer - Challenge-specific reviews
     * 7. User - Base role, submissions and collaboration
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // System Administration
            'manage_system',
            'view_system_metrics',
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_audit_logs',
            
            // User Management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'ban_users',
            'view_all_users',
            
            // Role Management  
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'assign_roles',
            
            // Idea Management
            'create_ideas',
            'edit_own_ideas',
            'delete_own_ideas',
            'view_all_ideas',
            'review_ideas',
            'approve_ideas',
            'reject_ideas',
            'archive_ideas',
            
            // Challenge Management
            'create_challenges',
            'edit_challenges',
            'delete_challenges',
            'view_all_challenges',
            'review_challenges',
            'approve_challenges',
            'reject_challenges',
            'manage_challenge_winners',
            'participate_challenges',
            'select_winners',
            
            // Review Process
            'conduct_manager_reviews',
            'conduct_sme_reviews',
            'conduct_board_reviews',
            'conduct_challenge_reviews',
            'view_review_assignments',
            'manage_review_workflow',
            
            // Collaboration
            'invite_collaborators',
            'accept_collaboration',
            'manage_collaborations',
            'view_collaboration_requests',
            
            // Communication
            'send_messages',
            'send_system_notifications',
            'manage_notifications',
            'view_all_messages',
            
            // Reports and Analytics
            'view_reports',
            'export_data',
            'view_analytics',
            'view_performance_metrics',
            
            // Profile Management
            'edit_own_profile',
            'view_profiles',
            'manage_staff_profiles',
            
            // Points and Gamification
            'award_points',
            'view_leaderboards',
            'manage_point_system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $this->createDeveloperRole();
        $this->createAdministratorRole();
        $this->createBoardMemberRole();
        $this->createManagerRole();
        $this->createSMERole();
        $this->createChallengeReviewerRole();
        $this->createUserRole();
    }

    private function createDeveloperRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'web'
        ]);

        // Developers have all permissions (system administration)
        $role->syncPermissions(Permission::all());
    }

    private function createAdministratorRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'administrator',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'manage_users',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'ban_users',
            'view_all_users',
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'assign_roles',
            'view_all_ideas',
            'view_all_challenges',
            'manage_review_workflow',
            'view_review_assignments',
            'send_system_notifications',
            'manage_notifications',
            'view_all_messages',
            'view_reports',
            'export_data',
            'view_analytics',
            'view_performance_metrics',
            'manage_staff_profiles',
            'award_points',
            'manage_point_system',
            'view_leaderboards',
            'edit_own_profile',
            'view_profiles',
            'send_messages',
        ];

        $role->syncPermissions($permissions);
    }

    private function createBoardMemberRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'board_member',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'view_all_ideas',
            'view_all_challenges',
            'conduct_board_reviews',
            'approve_ideas',
            'reject_ideas',
            'approve_challenges',
            'reject_challenges',
            'view_review_assignments',
            'view_reports',
            'view_analytics',
            'view_performance_metrics',
            'edit_own_profile',
            'view_profiles',
            'send_messages',
            'view_leaderboards',
        ];

        $role->syncPermissions($permissions);
    }

    private function createManagerRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'view_users',
            'create_users',
            'edit_users',
            'ban_users',
            'view_roles',
            'assign_roles',
            'create_challenges',
            'edit_challenges',
            'delete_challenges',
            'view_all_challenges',
            'manage_challenge_winners',
            'select_winners',
            'conduct_manager_reviews',
            'review_ideas',
            'view_review_assignments',
            'view_all_ideas',
            'create_ideas',
            'edit_own_ideas',
            'delete_own_ideas',
            'invite_collaborators',
            'accept_collaboration',
            'manage_collaborations',
            'view_collaboration_requests',
            'send_messages',
            'view_reports',
            'view_analytics',
            'edit_own_profile',
            'view_profiles',
            'view_leaderboards',
        ];

        $role->syncPermissions($permissions);
    }

    private function createSMERole()
    {
        $role = Role::firstOrCreate([
            'name' => 'sme',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'conduct_sme_reviews',
            'review_ideas',
            'view_review_assignments',
            'view_all_ideas',
            'create_ideas',
            'edit_own_ideas',
            'delete_own_ideas',
            'invite_collaborators',
            'accept_collaboration',
            'manage_collaborations',
            'view_collaboration_requests',
            'send_messages',
            'edit_own_profile',
            'view_profiles',
            'view_leaderboards',
        ];

        $role->syncPermissions($permissions);
    }

    private function createChallengeReviewerRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'challenge_reviewer',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'view_all_challenges',
            'conduct_challenge_reviews',
            'review_challenges',
            'view_review_assignments',
            'create_ideas',
            'edit_own_ideas',
            'delete_own_ideas',
            'send_messages',
            'edit_own_profile',
            'view_profiles',
            'view_leaderboards',
        ];

        $role->syncPermissions($permissions);
    }

    private function createUserRole()
    {
        $role = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web'
        ]);

        $permissions = [
            'create_ideas',
            'edit_own_ideas',
            'delete_own_ideas',
            'participate_challenges',
            'accept_collaboration',
            'view_collaboration_requests',
            'send_messages',
            'edit_own_profile',
            'view_profiles',
            'view_leaderboards',
        ];

        $role->syncPermissions($permissions);
    }
}
