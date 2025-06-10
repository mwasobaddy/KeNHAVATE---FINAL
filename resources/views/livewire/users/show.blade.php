<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\AuditLog;
use Livewire\WithPagination;

new #[Layout('components.layouts.app', title: 'User Details')] class extends Component {
    use WithPagination;
    
    public User $user;
    
    public function mount(User $user)
    {
        // Check permission to view users
        if (!auth()->user()->can('view_users')) {
            abort(403, 'Unauthorized access to user details.');
        }

        // Prevent non-developers from viewing developer users
        if (!auth()->user()->hasRole('developer') && $user->hasRole('developer')) {
            abort(403, 'You cannot view developer users.');
        }

        $this->user = $user->load('staff', 'roles', 'ideas', 'challengeSubmissions');
    }

    public function with()
    {
        // Get user audit logs with pagination
        $auditLogs = AuditLog::where('user_id', $this->user->id)
            ->orWhere(function ($query) {
                $query->where('entity_type', 'User')
                      ->where('entity_id', $this->user->id);
            })
            ->latest()
            ->paginate(10);

        return [
            'auditLogs' => $auditLogs,
            'totalIdeas' => $this->user->ideas()->count(),
            'totalSubmissions' => $this->user->challengeSubmissions()->count(),
            'totalReviews' => $this->user->reviews()->count(),
            'userPoints' => $this->user->userPoints()->sum('points'),
        ];
    }

}; ?>

{{-- Modern User Details with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 md:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header Background Gradient --}}
                <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/5"></div>
                
                <div class="relative z-10 p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                        <div class="flex items-center space-x-6">
                            {{-- Enhanced User Avatar --}}
                            <div class="relative">
                                <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 flex items-center justify-center shadow-xl transform hover:scale-105 transition-transform duration-300">
                                    <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-900">
                                        {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                    </span>
                                </div>
                                {{-- Status Indicator --}}
                                <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white dark:border-zinc-800 
                                    {{ $user->account_status === 'active' ? 'bg-green-500' : ($user->account_status === 'suspended' ? 'bg-yellow-500' : 'bg-red-500') }}">
                                </div>
                            </div>
                            
                            <div>
                                <h1 class="text-3xl font-bold text-[#231F20] dark:text-white mb-2">{{ $user->name }}</h1>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 mb-3">{{ $user->email }}</p>
                                
                                {{-- Enhanced Role & Status Badges --}}
                                <div class="flex flex-wrap gap-2">
                                    @php $role = $user->roles->first(); @endphp
                                    @if($role)
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full
                                            @if($role->name === 'developer') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300
                                            @elseif($role->name === 'administrator') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                            @elseif($role->name === 'board_member') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                            @elseif($role->name === 'manager') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                            @elseif($role->name === 'sme') bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300
                                            @elseif($role->name === 'challenge_reviewer') bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                            @endif">
                                            <div class="w-2 h-2 rounded-full mr-2
                                                @if($role->name === 'developer') bg-purple-500
                                                @elseif($role->name === 'administrator') bg-red-500
                                                @elseif($role->name === 'board_member') bg-blue-500
                                                @elseif($role->name === 'manager') bg-green-500
                                                @elseif($role->name === 'sme') bg-indigo-500
                                                @elseif($role->name === 'challenge_reviewer') bg-orange-500
                                                @else bg-gray-500
                                                @endif">
                                            </div>
                                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                        </span>
                                    @endif
                                    
                                    <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full
                                        @if($user->account_status === 'active') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                        @elseif($user->account_status === 'suspended') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                        @elseif($user->account_status === 'banned') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                        @endif">
                                        <div class="w-2 h-2 rounded-full mr-2 animate-pulse
                                            @if($user->account_status === 'active') bg-green-500
                                            @elseif($user->account_status === 'suspended') bg-yellow-500
                                            @elseif($user->account_status === 'banned') bg-red-500
                                            @else bg-gray-500
                                            @endif">
                                        </div>
                                        {{ ucfirst($user->account_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Enhanced Action Buttons --}}
                        <div class="flex flex-wrap gap-3">
                            @can('edit_users')
                            @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                            <flux:button 
                                variant="subtle" 
                                href="{{ route('users.edit', $user) }}" 
                                wire:navigate
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.pencil class="w-4 h-4 mr-2" />
                                    Edit User
                                </div>
                            </flux:button>
                            @endif
                            @endcan
                            
                            <flux:button 
                                variant="ghost" 
                                href="{{ route('users.index') }}" 
                                wire:navigate
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                                    Back to Users
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Statistics Cards with Glass Morphism --}}
        <section aria-labelledby="stats-heading" class="group">
            <h2 id="stats-heading" class="sr-only">User Activity Statistics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Ideas Submitted Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 flex items-center justify-center shadow-lg">
                                <flux:icon.light-bulb class="w-7 h-7 text-white" />
                            </div>
                            <div class="absolute -inset-2 bg-amber-500/20 dark:bg-amber-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Ideas Submitted</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-amber-600 dark:group-hover/card:text-amber-400 transition-colors duration-300">{{ number_format($totalIdeas) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-full">
                                <flux:icon.sparkles class="w-3 h-3" />
                                <span>Innovation count</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Challenge Submissions Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 flex items-center justify-center shadow-lg">
                                <flux:icon.trophy class="w-7 h-7 text-white" />
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Challenge Submissions</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-blue-600 dark:group-hover/card:text-blue-400 transition-colors duration-300">{{ number_format($totalSubmissions) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">
                                <flux:icon.puzzle-piece class="w-3 h-3" />
                                <span>Competitions</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reviews Given Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 flex items-center justify-center shadow-lg">
                                <flux:icon.chat-bubble-left-right class="w-7 h-7 text-white" />
                            </div>
                            <div class="absolute -inset-2 bg-emerald-500/20 dark:bg-emerald-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Reviews Given</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-emerald-600 dark:group-hover/card:text-emerald-400 transition-colors duration-300">{{ number_format($totalReviews) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-full">
                                <flux:icon.document-text class="w-3 h-3" />
                                <span>Evaluations</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Total Points Card --}}
                <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 ease-out">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-6">
                        <div class="relative mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 flex items-center justify-center shadow-lg">
                                <flux:icon.star class="w-7 h-7 text-white" />
                            </div>
                            <div class="absolute -inset-2 bg-purple-500/20 dark:bg-purple-400/30 rounded-2xl blur-xl opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        </div>
                        
                        <div>
                            <p class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 mb-2 uppercase tracking-wider">Total Points</p>
                            <p class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-3 group-hover/card:text-purple-600 dark:group-hover/card:text-purple-400 transition-colors duration-300">{{ number_format($userPoints) }}</p>
                            
                            <div class="inline-flex items-center space-x-2 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-full">
                                <flux:icon.fire class="w-3 h-3" />
                                <span>Gamification</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Main Content with Adaptive Layout --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {{-- User Information Section --}}
            <div class="xl:col-span-2 group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                    {{-- Header --}}
                    <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <flux:icon.user class="w-6 h-6 text-[#231F20]" />
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">User Information</h3>
                                <p class="text-[#9B9EA4] text-sm">Personal details and account information</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        {{-- Contact Information Section --}}
                        <div class="mb-8">
                            <h4 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 mb-4">Contact Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Email</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->email }}</span>
                                    </div>
                                </div>
                                
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Phone</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->phone ?? 'Not provided' }}</span>
                                    </div>
                                </div>
                                
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Gender</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->gender ? ucfirst($user->gender) : 'Not specified' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Account Information Section --}}
                        <div class="mb-8">
                            <h4 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100 mb-4">Account Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Joined</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>
                                
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Email Verified</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">
                                            {{ $user->email_verified_at ? $user->email_verified_at->format('M d, Y') : 'Not verified' }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Terms Accepted</span>
                                        <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">
                                            {{ $user->terms_accepted ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Staff Information (if applicable) --}}
                        @if($user->staff)
                        <div class="pt-6 border-t border-gray-100/50 dark:border-zinc-700/50">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                    <flux:icon.building-office class="w-5 h-5 text-white" />
                                </div>
                                <h4 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100">KeNHA Staff Information</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Staff Number</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->staff->staff_number }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Job Title</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->staff->job_title }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Department</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->staff->department }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-4">
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Personal Email</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->staff->personal_email }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Employment Type</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ ucfirst($user->staff->employment_type) }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="group/info relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400">Work Station</span>
                                            <span class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">{{ $user->staff->work_station }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Quick Actions Sidebar --}}
            <div class="space-y-6">
                @can('edit_users')
                @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                <div class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                        {{-- Header --}}
                        <div class="p-6 border-b border-gray-100/50 dark:border-zinc-700/50">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                                    <flux:icon.cog-6-tooth class="w-5 h-5 text-[#231F20]" />
                                </div>
                                <h3 class="text-lg font-semibold text-[#231F20] dark:text-zinc-100">Quick Actions</h3>
                            </div>
                        </div>
                        
                        <div class="p-6 space-y-3">
                            <flux:button 
                                variant="subtle" 
                                href="{{ route('users.edit', $user) }}" 
                                wire:navigate
                                class="w-full justify-start group relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-300"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    <flux:icon.pencil class="w-4 h-4 mr-3" />
                                    Edit User Details
                                </div>
                            </flux:button>
                            
                            @if($user->id !== auth()->id())
                            @can('ban_users')
                            <flux:button 
                                variant="{{ $user->account_status === 'banned' ? 'primary' : 'danger' }}" 
                                class="w-full justify-start group relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-300"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-red-500/10 to-red-600/20 dark:from-red-400/20 dark:to-red-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center">
                                    @if($user->account_status === 'banned')
                                        <flux:icon.check class="w-4 h-4 mr-3" />
                                        Unban User
                                    @else
                                        <flux:icon.x-mark class="w-4 h-4 mr-3" />
                                        Ban User
                                    @endif
                                </div>
                            </flux:button>
                            @endcan
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                @endcan
            </div>
        </div>

        {{-- Enhanced Audit Log Section --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header --}}
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <flux:icon.clock class="w-6 h-6 text-[#231F20]" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Activity Log</h3>
                            <p class="text-[#9B9EA4] text-sm">Recent activities and system events</p>
                        </div>
                    </div>
                </div>
                
                {{-- Audit Log Items --}}
                <div class="divide-y divide-gray-100/50 dark:divide-zinc-700/50">
                    @forelse($auditLogs as $log)
                        <div class="p-6 group/log hover:bg-white/50 dark:hover:bg-zinc-700/30 transition-colors duration-300">
                            <div class="flex items-start space-x-4">
                                {{-- Enhanced Activity Indicator --}}
                                <div class="flex-shrink-0 relative">
                                    <div class="w-3 h-3 rounded-full bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 mt-2 shadow-lg"></div>
                                    <div class="absolute -inset-1 bg-[#FFF200]/30 dark:bg-yellow-400/30 rounded-full blur-sm opacity-0 group-hover/log:opacity-100 transition-opacity duration-300"></div>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-semibold text-[#231F20] dark:text-zinc-100">
                                            {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                        </h4>
                                        <span class="text-xs text-[#9B9EA4] dark:text-zinc-400 bg-gray-100/50 dark:bg-zinc-700/50 px-2 py-1 rounded-full">
                                            {{ $log->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    
                                    @if($log->old_values || $log->new_values)
                                    <div class="mt-2 p-3 rounded-xl bg-gray-50/50 dark:bg-zinc-700/30 border border-gray-200/30 dark:border-zinc-600/30">
                                        @if($log->old_values && $log->new_values)
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mb-1">
                                                <span class="font-medium">Changed from:</span> {{ json_encode($log->old_values) }}
                                            </p>
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                <span class="font-medium">Changed to:</span> {{ json_encode($log->new_values) }}
                                            </p>
                                        @elseif($log->new_values)
                                            <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                <span class="font-medium">Details:</span> {{ json_encode($log->new_values) }}
                                            </p>
                                        @endif
                                    </div>
                                    @endif
                                    
                                    <div class="mt-2 flex items-center space-x-4 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                        <span class="inline-flex items-center">
                                            <flux:icon.globe-alt class="w-3 h-3 mr-1" />
                                            {{ $log->ip_address }}
                                        </span>
                                        <span class="inline-flex items-center">
                                            <flux:icon.calendar class="w-3 h-3 mr-1" />
                                            {{ $log->created_at->format('M d, Y \a\t g:i A') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                <flux:icon.clock class="w-8 h-8 text-[#231F20]" />
                            </div>
                            
                            <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Activity Logs Found</h4>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                No activity has been recorded for this user yet.
                            </p>
                        </div>
                    @endforelse
                </div>
                
                {{-- Enhanced Pagination --}}
                @if($auditLogs->hasPages())
                <div class="p-6 border-t border-gray-100/50 dark:border-zinc-700/50 bg-gradient-to-r from-white/30 to-white/10 dark:from-zinc-800/30 dark:to-zinc-700/10">
                    {{ $auditLogs->links() }}
                </div>
                @endif
            </div>
        </section>
    </div>
</div>
