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
    Volt::route('dashboard/idea-reviewer', 'dashboard.idea-reviewer-dashboard')->middleware('role:idea_reviewer')->name('dashboard.idea-reviewer');
});

Route::middleware(['auth', 'terms.accepted'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    
    // Ideas Management
    Volt::route('ideas', 'ideas.index')->name('ideas.index');
    Volt::route('ideas/create', 'ideas.create')->name('ideas.create')->middleware('role:user|manager|admin|sme|developer');
    Volt::route('ideas/{idea}', 'ideas.show')->name('ideas.show');
    Volt::route('ideas/{idea}/edit', 'ideas.edit')->name('ideas.edit');
    
    // Reviews Management
    Volt::route('reviews', 'reviews.index')->name('reviews.index')->middleware('role:manager|sme|board_member|idea_reviewer|admin');
    Volt::route('reviews/idea/{idea}', 'reviews.review-idea')->name('reviews.idea')->middleware('role:manager|sme|board_member|idea_reviewer|admin');
    
    // Challenge Competition System
    Volt::route('challenges', 'challenges.index')->name('challenges.index');
    Volt::route('challenges/create', 'challenges.create')->name('challenges.create')->middleware('role:manager|admin|developer');
    Volt::route('challenges/{challenge}', 'challenges.show')->name('challenges.show');
    Volt::route('challenges/{challenge}/edit', 'challenges.edit')->name('challenges.edit')->middleware('role:manager|admin|developer');
    Volt::route('challenges/{challenge}/submit', 'challenges.submit')->name('challenges.submit')->middleware('role:user|manager|admin|sme|developer');
    Volt::route('challenges/{challenge}/submissions', 'challenges.submissions')->name('challenges.submissions')->middleware('role:manager|challenge_reviewer|admin|developer');
    Volt::route('challenges/{challenge}/leaderboard', 'challenges.leaderboard')->name('challenges.leaderboard');
    
    // Challenge Reviews
    Volt::route('challenge-reviews', 'challenges.reviews')->name('challenge-reviews.index')->middleware('role:manager|challenge_reviewer|sme|admin|developer');
    Volt::route('challenge-reviews/{submission}', 'challenges.review-submission')->name('challenge-reviews.review')->middleware('role:manager|challenge_reviewer|sme|admin|developer');
    
    // Challenge Winner Selection
    Volt::route('challenges/{challenge}/winners', 'challenges.select-winners')->name('challenges.select-winners')->middleware('role:manager|admin|developer');
    
    // Collaboration Dashboard
    Volt::route('collaboration/dashboard', 'collaboration.dashboard')->name('collaboration.dashboard');
});

require __DIR__.'/auth.php';
