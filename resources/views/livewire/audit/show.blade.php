<?php
use App\Models\AuditLog;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', title: 'Audit Log Details')] class extends Component
{
    public AuditLog $log;

    public function mount($id)
    {
        $this->log = AuditLog::with('user')->findOrFail($id);
        $this->authorize('view', $this->log);
    }

    public function with()
    {
        return [
            'log' => $this->log,
        ];
    }
}; ?>

{{-- KeNHAVATE Audit Log Details - Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto py-10 space-y-8 lg:p-6">
        {{-- Header Section --}}
        <section class="group">
            <div class="flex items-center gap-4 mb-8">
                <flux:button 
                    wire:navigate 
                    href="{{ route('audit.index') }}" 
                    variant="ghost" 
                    size="sm"
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                >
                    <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                    <div class="relative flex items-center">
                        <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                        Back to Audit Logs
                    </div>
                </flux:button>
                <h1 class="text-3xl font-bold text-[#231F20] dark:text-white">Audit Log Details</h1>
            </div>
        </section>

        {{-- Audit Log Details Card --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-8 space-y-8">
                {{-- Statistics Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    {{-- Date --}}
                    <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider mb-1">Date</p>
                                <p class="text-lg font-bold text-[#231F20] dark:text-white">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                                <p class="text-sm text-amber-600 mt-1">{{ $log->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                                <flux:icon.calendar class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    {{-- User --}}
                    <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider mb-1">User</p>
                                <p class="text-lg font-bold text-[#231F20] dark:text-white">
                                    @if($log->user)
                                        {{ $log->user->name }}
                                    @else
                                        <span class="italic text-[#9B9EA4]">System</span>
                                    @endif
                                </p>
                                @if($log->user)
                                    <p class="text-sm text-blue-600 mt-1">{{ $log->user->email }}</p>
                                @endif
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                <flux:icon.user class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    {{-- Action --}}
                    <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider mb-1">Action</p>
                                <p class="text-lg font-bold text-[#231F20] dark:text-white capitalize">{{ str_replace('_', ' ', $log->action) }}</p>
                                <p class="text-sm text-purple-600 mt-1">{{ $log->description ?? '-' }}</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                <flux:icon.shield-check class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    {{-- Entity --}}
                    <div class="group/card relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 via-transparent to-green-600/10 dark:from-green-400/10 dark:via-transparent dark:to-green-500/20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#9B9EA4] uppercase tracking-wider mb-1">Entity</p>
                                <p class="text-lg font-bold text-[#231F20] dark:text-white">
                                    {{ $log->entity_type }} @if($log->entity_id) #{{ $log->entity_id }} @endif
                                </p>
                                <p class="text-sm text-green-600 mt-1">{{ $log->ip_address }}</p>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-xl flex items-center justify-center shadow-lg">
                                <flux:icon.key class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Details Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {{-- IP Address & User Agent --}}
                    <div class="space-y-4">
                        <div>
                            <div class="font-semibold text-[#231F20] dark:text-white flex items-center gap-2 mb-1">
                                <flux:icon.globe-alt class="w-4 h-4 text-blue-400" />
                                IP Address
                            </div>
                            <div class="text-[#231F20] dark:text-white">{{ $log->ip_address }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-[#231F20] dark:text-white flex items-center gap-2 mb-1">
                                <flux:icon.device-phone-mobile class="w-4 h-4 text-indigo-500" />
                                User Agent
                            </div>
                            <div class="break-all text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $log->user_agent }}</div>
                        </div>
                    </div>
                    {{-- Metadata --}}
                    <div>
                        <div class="font-semibold text-[#231F20] dark:text-white flex items-center gap-2 mb-1">
                            <flux:icon.document-text class="w-4 h-4 text-yellow-500" />
                            Metadata
                        </div>
                        <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto text-[#231F20] dark:text-white">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>

                {{-- Old/New Values --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <div class="font-semibold text-[#231F20] dark:text-white flex items-center gap-2 mb-1">
                            <flux:icon.arrow-uturn-left class="w-4 h-4 text-blue-500" />
                            Old Values
                        </div>
                        <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto text-[#231F20] dark:text-white">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    <div>
                        <div class="font-semibold text-[#231F20] dark:text-white flex items-center gap-2 mb-1">
                            <flux:icon.arrow-uturn-right class="w-4 h-4 text-green-500" />
                            New Values
                        </div>
                        <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto text-[#231F20] dark:text-white">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
