<x-layouts.app title="Credit notes">
    <div class="mx-auto max-w-7xl space-y-7">
        <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-xs tracking-widest text-zinc-500">FINANCE</p><h1 class="mt-3 text-4xl font-light">Credit notes</h1></div><flux:button :href="route('credit-notes.create')" variant="primary" wire:navigate>Create credit note ↗</flux:button></div>
        <x-save-errors />
        <form method="GET" class="max-w-sm"><flux:input name="search" :value="request('search')" placeholder="Search by number, invoice or client…" /><button type="submit" class="mt-2 text-xs">Search ↗</button></form>
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c]">
            <table class="w-full min-w-[650px] text-left text-sm"><thead class="text-xs text-zinc-500"><tr><th class="pb-4">Number</th><th>Issued</th><th>Client / invoice</th><th>Reason</th><th class="text-right">Total</th><th></th></tr></thead><tbody>
                @forelse($notes as $note)
                    <tr class="border-t border-zinc-100 dark:border-zinc-700"><td class="py-5"><a class="font-medium" href="{{ route('credit-notes.download', $note->id) }}">{{ $note->number }}</a></td><td>{{ $note->credited_on->format('d M Y') }}</td><td><a href="{{ route('documents.show', ['invoices', $note->invoice_id]) }}" wire:navigate>{{ $note->invoice->customer_name }} / {{ $note->invoice->invoice_number }}</a></td><td>{{ $note->reason }}</td><td class="text-right">{{ $note->invoice->currency }} {{ number_format($note->amount, 2) }}</td><td class="pl-5"><a href="{{ route('credit-notes.download', $note->id) }}">Download PDF ↗</a></td></tr>
                @empty<tr><td colspan="6" class="py-12 text-center text-zinc-500">No credit notes yet.</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $notes->links() }}
    </div>
</x-layouts.app>
