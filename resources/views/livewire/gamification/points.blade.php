<?php

use Livewire\Volt\Component;

new class extends Component
{
    // This page is a thin wrapper around existing points components
    // All functionality is handled by the reusable components
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#231F20]">Points & History</h1>
                <p class="mt-2 text-[#9B9EA4]">Track your points, achievements, and contribution history</p>
            </div>
            <div class="flex items-center space-x-2">
                <flux:icon.currency-dollar class="h-8 w-8 text-emerald-600" />
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Points Widget --}}
        <div>
            <livewire:components.points-widget />
        </div>
        
        {{-- Points History --}}
        <div>
            <livewire:components.points-history />
        </div>
    </div>
</div>
