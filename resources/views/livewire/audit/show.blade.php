@php
use App\Models\AuditLog;
@endphp

<main class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('audit.index') }}" class="text-[#9B9EA4] hover:text-[#231F20] dark:hover:text-white flex items-center gap-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back to Audit Logs
        </a>
        <h1 class="text-2xl font-bold text-[#231F20] dark:text-white">Audit Log Details</h1>
    </div>

    <div class="bg-white dark:bg-zinc-900 rounded-lg shadow p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">Date</div>
                <div>{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">User</div>
                <div>
                    @if($log->user)
                        {{ $log->user->name }} <span class="text-xs text-[#9B9EA4]">({{ $log->user->email }})</span>
                    @else
                        <span class="italic text-[#9B9EA4]">System</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">Action</div>
                <div>{{ str_replace('_', ' ', $log->action) }}</div>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">Entity</div>
                <div>{{ $log->entity_type }} @if($log->entity_id) #{{ $log->entity_id }} @endif</div>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">IP Address</div>
                <div>{{ $log->ip_address }}</div>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">User Agent</div>
                <div class="break-all text-xs">{{ $log->user_agent }}</div>
            </div>
        </div>
        <div>
            <div class="font-semibold text-[#231F20] dark:text-white">Description</div>
            <div class="text-sm">{{ $log->description ?? '-' }}</div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">Old Values</div>
                <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div>
                <div class="font-semibold text-[#231F20] dark:text-white">New Values</div>
                <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
        <div>
            <div class="font-semibold text-[#231F20] dark:text-white">Metadata</div>
            <pre class="bg-[#F8EBD5]/40 dark:bg-zinc-800/40 rounded p-2 text-xs overflow-x-auto">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</main>
