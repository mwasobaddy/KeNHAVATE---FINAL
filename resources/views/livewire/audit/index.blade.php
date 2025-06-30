@php
use App\Models\AuditLog;
@endphp

<main class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-[#231F20] dark:text-white">Audit Logs</h1>
        <form method="get" class="flex gap-2">
            <flux:input name="search" value="{{ request('search') }}" placeholder="Search by user, action, IP..." class="w-64" />
            <flux:button type="submit" variant="primary">Search</flux:button>
        </form>
    </div>

    <div class="relative overflow-x-auto shadow-md sm:rounded-lg bg-white dark:bg-zinc-900">
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-300">
            <thead class="text-xs uppercase bg-[#F8EBD5] dark:bg-zinc-800 text-[#231F20] dark:text-white">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Entity</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b border-[#F8EBD5] dark:border-zinc-800 hover:bg-[#FFF200]/10 dark:hover:bg-zinc-800/30 transition">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            @if($log->user)
                                <span class="font-semibold">{{ $log->user->name }}</span>
                                <span class="block text-xs text-[#9B9EA4] dark:text-zinc-400">{{ $log->user->email }}</span>
                            @else
                                <span class="italic text-[#9B9EA4]">System</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ str_replace('_', ' ', $log->action) }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $log->entity_type }} @if($log->entity_id) #{{ $log->entity_id }} @endif</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $log->ip_address }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <a href="{{ route('audit.show', $log->id) }}" class="text-[#FFF200] hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-[#9B9EA4]">No audit logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">
            {{ $logs->links() }}
        </div>
    </div>
</main>
