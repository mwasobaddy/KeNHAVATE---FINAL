<?php

use Livewire\Volt\Component;

new class extends Component
{
    // This page is a thin wrapper around the existing leaderboard component
    // All functionality is handled by the reusable component
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#231F20]">Leaderboard</h1>
                <p class="mt-2 text-[#9B9EA4]">See how you rank among your peers and colleagues</p>
            </div>
            <div class="flex items-center space-x-2">
                <flux:icon.trophy class="h-8 w-8 text-[#FFF200]" />
            </div>
        </div>
    </div>

    {{-- Use the existing leaderboard component in full-page mode --}}
    <livewire:components.leaderboard :mini="false" :admin-view="false" />
</div>

