<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
