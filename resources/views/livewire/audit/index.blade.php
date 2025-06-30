<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\AuditLog;

new #[Layout('components.layouts.app', title: 'Audit Logs')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sort_by = 'created_at';
    public string $sort_direction = 'desc';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sort_by === $field) {
            $this->sort_direction = $this->sort_direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort_by = $field;
            $this->sort_direction = 'asc';
        }
        $this->resetPage();
    }

    public function with()
    {
        $logs = AuditLog::with('user')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('ip_address', 'like', "%{$this->search}%")
                      ->orWhere('action', 'like', "%{$this->search}%")
                      ->orWhere('entity_type', 'like', "%{$this->search}%")
                      ->orWhereHas('user', function ($uq) {
                          $uq->where('name', 'like', "%{$this->search}%")
                             ->orWhere('email', 'like', "%{$this->search}%");
                      });
                });
            })
            ->orderBy($this->sort_by, $this->sort_direction)
            ->paginate(20);

        return [
            'logs' => $logs,
        ];
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 lg:p-6 space-y-8 max-w-7xl mx-auto">
        {{-- Header Section --}}
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-[#231F20] dark:text-white mb-4">
                Audit Logs
            </h1>
            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                Comprehensive platform activity & security trail
            </p>
        </div>

        {{-- Actions and Search Section --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-row gap-4 items-center justify-between">
                {{-- Search Input --}}
                <div class="flex-1 w-full lg:w-1/3">
                    <flux:input
                        wire:model.live.debounce.400ms="search"
                        placeholder="Search by user, action, IP..."
                        class="bg-white/90 dark:bg-zinc-700/90 backdrop-blur-sm border border-[#9B9EA4]/30 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <flux:icon.magnifying-glass slot="leading" class="w-4 h-4 text-[#9B9EA4]" />
                    </flux:input>
                </div>
            </div>
        </div>

        {{-- Audit Logs Table --}}
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6 border-b border-white/20 dark:border-zinc-700/50">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center">
                        <flux:icon.rectangle-stack class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#231F20] dark:text-white">Audit Trail</h3>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">All platform activity and security events</p>
                    </div>
                </div>
            </div>

            <div class="relative overflow-x-auto">
                {{-- Skeleton Loader --}}
                <div wire:loading class="absolute inset-0 z-10 flex flex-col space-y-2 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm rounded-3xl animate-pulse">
                    @for($i = 0; $i < 8; $i++)
                        <div class="h-8 mx-8 my-2 rounded bg-[#F8EBD5]/60 dark:bg-zinc-800/60"></div>
                    @endfor
                </div>
                <table wire:loading.remove class="w-full text-sm text-left text-gray-500 dark:text-gray-300">
                    <thead class="bg-white/50 dark:bg-zinc-700/50 backdrop-blur-sm">
                        <tr>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white cursor-pointer select-none" wire:click="sortBy('created_at')">
                                <span class="flex items-center">
                                    Date
                                    @if($sort_by === 'created_at')
                                        <svg class="w-3 h-3 ml-1 {{ $sort_direction === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @endif
                                </span>
                            </th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">User</th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">Action</th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">Entity</th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">IP</th>
                            <th class="px-8 py-4 text-left text-sm font-semibold text-[#231F20] dark:text-white">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/20 dark:divide-zinc-700/50">
                        @forelse($logs as $log)
                            <tr class="hover:bg-white/30 dark:hover:bg-zinc-700/30 transition-all duration-200">
                                <td class="px-8 py-6 whitespace-nowrap font-semibold text-[#231F20] dark:text-zinc-100">
                                    {{ $log->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-8 py-6 whitespace-nowrap">
                                    @if($log->user)
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center text-white font-semibold">
                                                {{ $log->user->initials() ?? substr($log->user->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <span class="font-medium text-[#231F20] dark:text-zinc-100">{{ $log->user->name }}</span>
                                                <span class="block text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $log->user->email }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="italic text-[#9B9EA4]">System</span>
                                    @endif
                                </td>
                                <td class="px-8 py-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                        {{ str_replace('_', ' ', $log->action) }}
                                    </span>
                                </td>
                                <td class="px-8 py-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                        {{ $log->entity_type }} @if($log->entity_id) #{{ $log->entity_id }} @endif
                                    </span>
                                </td>
                                <td class="px-8 py-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-50 dark:bg-gray-700/30 text-gray-700 dark:text-gray-300">
                                        {{ $log->ip_address }}
                                    </span>
                                </td>
                                <td class="px-8 py-6 whitespace-nowrap">
                                    {{-- <a href="{{ route('audit.show', $log->id) }}" class="text-[#FFF200] hover:underline font-semibold">View</a> --}}
                                    <flux:button 
                                        wire:navigate 
                                        href="{{ route('audit.show', $log->id) }}" 
                                        variant="ghost" 
                                        size="sm"
                                        class="bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white border-0 rounded-lg transition-all duration-300 hover:shadow-lg transform hover:scale-105 px-3 py-5"
                                    >
                                        <flux:icon.eye class="w-4 h-4" />
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center space-y-4">
                                        <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                                            <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <h4 class="text-lg font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Audit Logs Found</h4>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm leading-relaxed">
                                            No audit trail records match your search.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-6">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
