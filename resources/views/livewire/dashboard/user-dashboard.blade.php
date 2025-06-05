<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Idea;
use App\Models\Challenge;

new #[Layout('components.layouts.app', title: 'User Dashboard')] class extends Component {
    
    public function with(): array
    {
        $user = auth()->user();
        
        return [
            'myIdeas' => Idea::where('author_id', $user->id)->latest()->take(5)->get(),
            'mySubmissions' => [], // TODO: Challenge submissions when implemented
            'availableChallenges' => Challenge::where('status', 'active')
                ->where('deadline', '>', now())
                ->latest()
                ->take(3)
                ->get(),
            'stats' => [
                'total_ideas' => Idea::where('author_id', $user->id)->count(),
                'ideas_in_review' => Idea::where('author_id', $user->id)
                    ->whereIn('current_stage', ['manager_review', 'sme_review', 'board_review'])
                    ->count(),
                'completed_ideas' => Idea::where('author_id', $user->id)
                    ->where('current_stage', 'completed')
                    ->count(),
                'collaboration_invites' => 0, // TODO: Implement when collaboration features are ready
            ]
        ];
    }
    
}; ?>


<div class="space-y-6">
    <!-- User Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Ideas -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">My Ideas</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['total_ideas'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Ideas in Review -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">In Review</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['ideas_in_review'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Completed Ideas -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Completed</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['completed_ideas'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Collaboration Invites -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[#9B9EA4] font-medium">Invitations</p>
                    <p class="text-3xl font-bold text-[#231F20]">{{ $stats['collaboration_invites'] }}</p>
                </div>
                <div class="w-12 h-12 bg-[#F8EBD5] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- My Ideas Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Ideas -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-[#231F20]">My Recent Ideas</h3>
                <flux:button href="#" variant="ghost" size="sm">
                    View All
                </flux:button>
            </div>
            <div class="space-y-3">
                @forelse($myIdeas as $idea)
                    <div class="border border-[#9B9EA4] rounded-lg p-4">
                        <h4 class="font-medium text-[#231F20] mb-1">{{ $idea->title }}</h4>
                        <p class="text-sm text-[#9B9EA4] mb-2">{{ Str::limit($idea->description, 100) }}</p>
                        <div class="flex justify-between items-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                @if($idea->current_stage === 'draft') bg-gray-100 text-gray-800
                                @elseif($idea->current_stage === 'submitted') bg-blue-100 text-blue-800
                                @elseif(in_array($idea->current_stage, ['manager_review', 'sme_review', 'board_review'])) bg-yellow-100 text-yellow-800
                                @elseif($idea->current_stage === 'completed') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucwords(str_replace('_', ' ', $idea->current_stage)) }}
                            </span>
                            <span class="text-xs text-[#9B9EA4]">{{ $idea->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        <p class="mt-2 text-[#9B9EA4]">No ideas submitted yet</p>
                        <flux:button href="#" variant="primary" size="sm" class="mt-3">
                            Submit Your First Idea
                        </flux:button>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Available Challenges -->
        <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-[#231F20]">Available Challenges</h3>
                <flux:button href="#" variant="ghost" size="sm">
                    View All
                </flux:button>
            </div>
            <div class="space-y-3">
                @forelse($availableChallenges as $challenge)
                    <div class="border border-[#9B9EA4] rounded-lg p-4">
                        <h4 class="font-medium text-[#231F20] mb-1">{{ $challenge->title }}</h4>
                        <p class="text-sm text-[#9B9EA4] mb-2">{{ Str::limit($challenge->description, 100) }}</p>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-[#9B9EA4]">
                                Deadline: {{ $challenge->deadline?->format('M j, Y') }}
                            </span>
                            <flux:button href="#" variant="primary" size="sm">
                                Participate
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <p class="mt-2 text-[#9B9EA4]">No active challenges available</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Innovation Tips -->
    <div class="bg-white p-6 rounded-lg border border-[#9B9EA4]">
        <h3 class="text-lg font-semibold text-[#231F20] mb-4">Innovation Tips</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">Think Big, Start Small</h4>
                <p class="text-sm text-[#9B9EA4]">Great innovations often begin with simple observations. Don't be afraid to submit ideas that seem small at first.</p>
            </div>
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">Collaborate & Connect</h4>
                <p class="text-sm text-[#9B9EA4]">Some of the best ideas come from collaboration. Connect with colleagues to enhance your proposals.</p>
            </div>
            <div class="p-4 bg-[#F8EBD5] rounded">
                <h4 class="font-medium text-[#231F20] mb-2">Focus on Impact</h4>
                <p class="text-sm text-[#9B9EA4]">Consider how your idea will improve road infrastructure, safety, or efficiency for Kenyan citizens.</p>
            </div>
        </div>
    </div>
</div>
