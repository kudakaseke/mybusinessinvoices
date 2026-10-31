<x-layouts.app title="Saved drafts">
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-semibold">Saved drafts</h1><flux:button :href="route('documents.create', 'invoices')" wire:navigate>New invoice</flux:button></div>
        <x-save-errors />
        <p class="text-sm text-zinc-500">Drafts do not affect invoices, balances, or reports until reviewed and saved as final documents.</p>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="pb-4">Document</th><th>Client</th><th>Last saved</th><th>Actions</th></tr></thead><tbody>
        @forelse($drafts as $draft)<tr class="border-t border-zinc-200 dark:border-zinc-700">
            <td class="py-4 pr-4">{{ ucfirst(rtrim($draft->kind, 's')) }} {{ $draft->payload[$draft->kind === 'invoices' ? 'invoice_number' : 'quotation_number'] ?? '' }}</td>
            <td>{{ $draft->payload['customer_name'] ?? 'No client yet' }}</td>
            <td>{{ $draft->updated_at->timezone('Africa/Johannesburg')->format('d M Y H:i') }}</td>
            <td class="flex flex-wrap gap-4 py-4"><a class="underline" href="{{ route('documents.create', [$draft->kind, 'draft' => $draft->key]) }}" wire:navigate>Continue editing</a>
                <form method="POST" action="{{ route('drafts.destroy', $draft->key) }}" x-data x-on:submit="if (!confirm('Delete this unfinished draft?')) $event.preventDefault()">@csrf @method('DELETE')<button type="submit" class="text-red-600">Delete draft</button></form>
            </td>
        </tr>@empty<tr><td colspan="4" class="py-6 text-zinc-500">No saved drafts yet.</td></tr>@endforelse
        </tbody></table></div>
        {{ $drafts->links() }}
    </div>
</x-layouts.app>
