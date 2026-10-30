<x-layouts.app title="Create credit note">
    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('credit-notes.index') }}" class="text-sm" wire:navigate>&larr; Credit notes</a>
        <h1 class="text-3xl font-semibold">Create credit note</h1>
        <x-save-errors />
        <form method="GET" action="{{ route('credit-notes.create') }}" class="flex flex-wrap items-end gap-4">
            <flux:select name="invoice" label="Original invoice" required>
                <option value="">Choose an invoice</option>
                @foreach($invoices as $option)<option value="{{ $option->id }}" @selected($invoice?->id === $option->id)>{{ $option->invoice_number }} · {{ $option->customer_name }} · {{ $option->currency }} {{ number_format($option->creditRemaining(), 2) }} available to credit</option>@endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Continue</flux:button>
        </form>
        @if($invoice && (float) $invoice->creditRemaining() > 0)
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c]">
                <h2 class="mb-4 text-xl font-semibold">{{ $invoice->invoice_number }} · {{ $invoice->customer_name }}</h2>
                <x-credit-note-form :document="$invoice" />
            </section>
        @elseif($invoice)
            <p class="text-sm text-zinc-500">This invoice has already been fully credited.</p>
        @elseif($invoices->isEmpty())
            <p class="text-sm text-zinc-500">No invoices are available to credit. Create an invoice first.</p>
            <flux:button :href="route('documents.create', 'invoices')" wire:navigate>Create invoice</flux:button>
        @endif
    </div>
</x-layouts.app>
