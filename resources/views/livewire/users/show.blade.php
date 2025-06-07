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

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">{{ $user->name }}</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 mt-1">User Details and Activity</p>
                </div>
                
                <div class="flex space-x-3">
                    @can('edit_users')
                    @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                    <flux:button 
                        variant="subtle" 
                        href="{{ route('users.edit', $user) }}" 
                        wire:navigate
                    >
                        <flux:icon.pencil class="w-4 h-4 mr-2" />
                        Edit User
                    </flux:button>
                    @endif
                    @endcan
                    
                    <flux:button 
                        variant="ghost" 
                        href="{{ route('users.index') }}" 
                        wire:navigate
                    >
                        <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                        Back to Users
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- User Information Card -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-16 h-16 rounded-full bg-[#FFF200]/20 flex items-center justify-center">
                                    <span class="text-2xl font-bold text-[#231F20] dark:text-white">
                                        {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-white">{{ $user->name }}</h2>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400">{{ $user->email }}</p>
                                    
                                    <!-- Role Badge -->
                                    @php $role = $user->roles->first(); @endphp
                                    @if($role)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full mt-1
                                            @if($role->name === 'developer') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300
                                            @elseif($role->name === 'administrator') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                            @elseif($role->name === 'board_member') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                            @elseif($role->name === 'manager') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                            @elseif($role->name === 'sme') bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300
                                            @elseif($role->name === 'challenge_reviewer') bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                        </span>
                                    @endif
                                    
                                    <!-- Status Badge -->
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full mt-1 ml-2
                                        @if($user->account_status === 'active') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                        @elseif($user->account_status === 'suspended') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                                        @elseif($user->account_status === 'banned') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300
                                        @endif">
                                        {{ ucfirst($user->account_status) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Basic Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 mb-2">Contact Information</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Email:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->email }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Phone:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->phone ?? 'Not provided' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Gender:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->gender ? ucfirst($user->gender) : 'Not specified' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-medium text-[#9B9EA4] dark:text-zinc-400 mb-2">Account Information</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Joined:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->created_at->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Email Verified:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                {{ $user->email_verified_at ? $user->email_verified_at->format('M d, Y') : 'Not verified' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Terms Accepted:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                {{ $user->terms_accepted ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Staff Information (if applicable) -->
                            @if($user->staff)
                            <div class="mt-6 pt-6 border-t border-[#9B9EA4]/20">
                                <h3 class="text-lg font-medium text-[#231F20] dark:text-white mb-4">KeNHA Staff Information</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Staff Number:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->staff_number }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Job Title:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->job_title }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Department:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->department }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Work Station:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->work_station }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Personal Email:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->personal_email }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Employment Type:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ ucfirst($user->staff->employment_type) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Employment Date:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->employment_date?->format('M d, Y') ?? 'Not set' }}</span>
                                        </div>
                                        @if($user->staff->supervisor_name)
                                        <div class="flex justify-between">
                                            <span class="text-sm text-[#231F20] dark:text-white">Supervisor:</span>
                                            <span class="text-sm text-[#9B9EA4] dark:text-zinc-400">{{ $user->staff->supervisor_name }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="space-y-6">
                    <!-- Activity Statistics -->
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h3 class="text-lg font-medium text-[#231F20] dark:text-white mb-4">Activity Statistics</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <flux:icon.light-bulb class="w-5 h-5 text-yellow-500 mr-2" />
                                    <span class="text-sm text-[#231F20] dark:text-white">Ideas Submitted</span>
                                </div>
                                <span class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $totalIdeas }}</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <flux:icon.trophy class="w-5 h-5 text-blue-500 mr-2" />
                                    <span class="text-sm text-[#231F20] dark:text-white">Challenge Submissions</span>
                                </div>
                                <span class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $totalSubmissions }}</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <flux:icon.chat-bubble-left-right class="w-5 h-5 text-green-500 mr-2" />
                                    <span class="text-sm text-[#231F20] dark:text-white">Reviews Given</span>
                                </div>
                                <span class="text-sm font-semibold text-[#231F20] dark:text-white">{{ $totalReviews }}</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <flux:icon.star class="w-5 h-5 text-purple-500 mr-2" />
                                    <span class="text-sm text-[#231F20] dark:text-white">Total Points</span>
                                </div>
                                <span class="text-sm font-semibold text-[#231F20] dark:text-white">{{ number_format($userPoints) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    @can('edit_users')
                    @if(auth()->user()->hasRole('developer') || !$user->hasRole('developer'))
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 p-6">
                        <h3 class="text-lg font-medium text-[#231F20] dark:text-white mb-4">Quick Actions</h3>
                        
                        <div class="space-y-3">
                            <flux:button 
                                variant="subtle" 
                                href="{{ route('users.edit', $user) }}" 
                                wire:navigate
                                class="w-full justify-start"
                            >
                                <flux:icon.pencil class="w-4 h-4 mr-2" />
                                Edit User Details
                            </flux:button>
                            
                            @if($user->id !== auth()->id())
                            @can('ban_users')
                            <flux:button 
                                variant="{{ $user->account_status === 'banned' ? 'primary' : 'danger' }}" 
                                class="w-full justify-start"
                            >
                                @if($user->account_status === 'banned')
                                    <flux:icon.check class="w-4 h-4 mr-2" />
                                    Unban User
                                @else
                                    <flux:icon.x-mark class="w-4 h-4 mr-2" />
                                    Ban User
                                @endif
                            </flux:button>
                            @endcan
                            @endif
                        </div>
                    </div>
                    @endif
                    @endcan
                </div>
            </div>

            <!-- Audit Log Section -->
            <div class="mt-8">
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
                    <div class="p-6 border-b border-[#9B9EA4]/20">
                        <h3 class="text-lg font-medium text-[#231F20] dark:text-white">Activity Log</h3>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-1">Recent activities and system events</p>
                    </div>
                    
                    <div class="divide-y divide-[#9B9EA4]/20">
                        @forelse($auditLogs as $log)
                            <div class="p-6">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-2 h-2 rounded-full bg-[#FFF200] mt-2"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-sm font-medium text-[#231F20] dark:text-white">
                                                {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                            </h4>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                                {{ $log->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        
                                        @if($log->old_values || $log->new_values)
                                        <div class="mt-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                            @if($log->old_values && $log->new_values)
                                                <p>Changed from: {{ json_encode($log->old_values) }}</p>
                                                <p>Changed to: {{ json_encode($log->new_values) }}</p>
                                            @elseif($log->new_values)
                                                <p>Details: {{ json_encode($log->new_values) }}</p>
                                            @endif
                                        </div>
                                        @endif
                                        
                                        <div class="mt-1 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                            IP: {{ $log->ip_address }} • {{ $log->created_at->format('M d, Y \a\t g:i A') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center">
                                <flux:icon.clock class="w-8 h-8 mx-auto text-[#9B9EA4] dark:text-zinc-400 mb-2" />
                                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">No activity logs found.</p>
                            </div>
                        @endforelse
                    </div>
                    
                    @if($auditLogs->hasPages())
                    <div class="p-6 border-t border-[#9B9EA4]/20">
                        {{ $auditLogs->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
