<x-layouts.app title="Activity history">
    <div class="mx-auto max-w-6xl space-y-6">
        <h1 class="text-3xl font-semibold">Activity history</h1>
        <p class="text-sm text-zinc-500">Changes recorded from when activity tracking was enabled. Earlier changes cannot be reconstructed.</p>
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <flux:input type="date" name="from" label="From" :value="request('from')" />
            <flux:input type="date" name="to" label="To" :value="request('to')" />
            <flux:button type="submit">Filter</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <table class="w-full text-left text-sm"><thead><tr><th class="pb-4">Date and time</th><th>Activity</th><th>Details</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="py-4 pr-4">{{ $log->created_at->timezone('Africa/Johannesburg')->format('d M Y H:i') }}</td><td class="pr-4">{{ $log->description }}</td><td>
                    @if($log->changes)<details><summary class="cursor-pointer">View details</summary><pre class="mt-2 max-w-lg overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                </td></tr>
            @empty<tr><td colspan="3" class="py-6 text-zinc-500">No activity recorded for this period.</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $logs->links() }}
    </div>
</x-layouts.app>
