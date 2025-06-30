<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Account status pages (no auth required as users can't authenticate)
Volt::route('banned-account', 'auth.banned-account')->name('banned-account');
Volt::route('suspended-account', 'auth.suspended-account')->name('suspended-account');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'terms.accepted'])
    ->name('dashboard');

// Role-specific dashboards using Volt routes for proper Livewire component handling
Route::middleware(['auth', 'verified', 'terms.accepted'])->group(function () {
    Volt::route('dashboard/user', 'dashboard.user-dashboard')->name('dashboard.user');
    Volt::route('dashboard/admin', 'dashboard.admin-dashboard')->middleware('role:developer|administrator')->name('dashboard.admin');
    Volt::route('dashboard/board-member', 'dashboard.board-member-dashboard')->middleware('role:board_member')->name('dashboard.board-member');
    Volt::route('dashboard/manager', 'dashboard.manager-dashboard')->middleware('role:manager')->name('dashboard.manager');
    Volt::route('dashboard/sme', 'dashboard.sme-dashboard')->middleware('role:sme')->name('dashboard.sme');
    Volt::route('dashboard/challenge-reviewer', 'dashboard.challenge-reviewer-dashboard')->middleware('role:challenge_reviewer')->name('dashboard.challenge-reviewer');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
        
    Route::middleware(['verified','terms.accepted'])->group(function () {
        // Ideas Management
        Volt::route('ideas', 'ideas.index')->name('ideas.index');
        Volt::route('ideas/create', 'ideas.create')->name('ideas.create')->middleware('permission:create_ideas');
        Volt::route('ideas/{idea}', 'ideas.show')->name('ideas.show');
        Volt::route('ideas/{idea}/edit', 'ideas.edit')->name('ideas.edit');
        
        // Reviews Management
        Volt::route('reviews', 'reviews.index')->name('reviews.index')->middleware('permission:review_ideas');
        Volt::route('reviews/idea/{idea}', 'reviews.review-idea')->name('reviews.idea')->middleware('permission:review_ideas');
        
        // Challenge Competition System
        Volt::route('challenges', 'challenges.index')->name('challenges.index');
        Volt::route('challenges/create', 'challenges.create')->name('challenges.create')->middleware('permission:create_challenges');
        Volt::route('challenges/{challenge}', 'challenges.show')->name('challenges.show');
        Volt::route('challenges/{challenge}/edit', 'challenges.edit')->name('challenges.edit')->middleware('permission:edit_challenges');
        Volt::route('challenges/{challenge}/submit', 'challenges.submit')->name('challenges.submit')->middleware('permission:participate_challenges');
        Volt::route('challenges/{challenge}/submissions', 'challenges.submissions')->name('challenges.submissions')->middleware('permission:review_challenges');
        Volt::route('challenges/{challenge}/leaderboard', 'challenges.leaderboard')->name('challenges.leaderboard');
        
        // Challenge Reviews
        Volt::route('challenge-reviews', 'challenges.reviews')->name('challenge-reviews.index')->middleware('permission:review_challenges');
        Volt::route('challenge-reviews/{submission}', 'challenges.review-submission')->name('challenge-reviews.review')->middleware('permission:review_challenges');
        
        // Challenge Winner Selection
        Volt::route('challenges/{challenge}/winners', 'challenges.select-winners')->name('challenges.select-winners')->middleware('permission:select_winners');
        
        // Collaboration Dashboard
        Volt::route('collaboration/dashboard', 'collaboration.dashboard')->name('collaboration.dashboard');
        
        // Community Features
        Volt::route('community/collaboration/{idea}', 'community.collaboration-dashboard')->name('community.collaboration');
        Volt::route('community/manage/{idea}', 'community.collaboration-management')->name('community.manage');
        
        // Gamification System
        Volt::route('gamification/leaderboard', 'gamification.leaderboard')->name('gamification.leaderboard');
        Volt::route('gamification/points', 'gamification.points')->name('gamification.points');
        Volt::route('gamification/achievements', 'gamification.achievements')->name('gamification.achievements');
        
        // Analytics & Reporting
        Volt::route('analytics', 'analytics.advanced-dashboard')->name('analytics.dashboard')->middleware('permission:view_analytics');
        
        // User Management - Permission-based access
        Route::middleware(['permission:create_users'])->group(function () {
            Volt::route('users/create', 'users.create')->name('users.create');
        });

        Route::middleware(['permission:view_users'])->group(function () {
            Volt::route('users', 'users.index')->name('users.index');
            Volt::route('users/{user}', 'users.show')->name('users.show');
        });
        
        Route::middleware(['permission:edit_users'])->group(function () {
            Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
        });
        
        // Role Management - Permission-based access  
        // IMPORTANT: Specific routes must come before wildcard routes to prevent conflicts
        Route::middleware(['permission:create_roles'])->group(function () {
            Volt::route('roles/create', 'roles.create')->name('roles.create');
        });
        
        Route::middleware(['permission:view_roles'])->group(function () {
            Volt::route('roles', 'roles.index')->name('roles.index');
            Volt::route('roles/{role}', 'roles.show')->name('roles.show');
        });
        
        Route::middleware(['permission:edit_roles'])->group(function () {
            Volt::route('roles/{role}/edit', 'roles.edit')->name('roles.edit');
        });
        
        // Developer-only routes (keep role-based for system security)
        Route::middleware(['role:developer'])->group(function () {
            // System administration routes that only developers should access
            Volt::route('system/logs', 'system.logs')->name('system.logs');
            Volt::route('system/maintenance', 'system.maintenance')->name('system.maintenance');
        });
        
        // Audit Log Management (Developer & Admin only)
        Route::middleware(['permission:view_audit_logs'])->group(function () {
            Volt::route('audit', 'audit.index')->name('audit.index');
            Volt::route('audit/{id}', 'audit.show')->name('audit.show');
        });
    });
});

require __DIR__.'/auth.php';
