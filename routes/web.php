<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Role-specific dashboards
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard/user', 'livewire.dashboard.user-dashboard')->name('dashboard.user');
    Route::view('dashboard/admin', 'livewire.dashboard.admin-dashboard')->middleware('role:developer|administrator')->name('dashboard.admin');
    Route::view('dashboard/board-member', 'livewire.dashboard.board-member-dashboard')->middleware('role:board_member')->name('dashboard.board-member');
    Route::view('dashboard/manager', 'livewire.dashboard.manager-dashboard')->middleware('role:manager')->name('dashboard.manager');
    Route::view('dashboard/sme', 'livewire.dashboard.sme-dashboard')->middleware('role:sme')->name('dashboard.sme');
    Route::view('dashboard/challenge-reviewer', 'livewire.dashboard.challenge-reviewer-dashboard')->middleware('role:challenge_reviewer')->name('dashboard.challenge-reviewer');
    Route::view('dashboard/idea-reviewer', 'livewire.dashboard.idea-reviewer-dashboard')->middleware('role:idea_reviewer')->name('dashboard.idea-reviewer');
});

Route::middleware(['auth'])->group(function () {
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
});

require __DIR__.'/auth.php';
