<x-layouts.app title="Statements">
    <div class="mx-auto max-w-7xl space-y-6">
        <div><h1 class="text-3xl font-semibold">Statements</h1><p class="mt-2 text-sm text-zinc-500">See what each client was charged, what they paid, and what they still owe.</p></div>
        <x-save-errors />
        <details class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-[#10261c]">
            <summary class="cursor-pointer font-semibold">Record a payment</summary>
            <p class="mt-3 text-sm text-zinc-500">Choose the invoice the client is paying. Payment choices follow the selected client and currency.</p>
            <ul class="mt-3 space-y-2">@forelse($paymentInvoices as $invoice)
                <li><a class="text-sm underline" href="{{ route('documents.show', ['invoices', $invoice->id, 'record_payment' => 1]).'#record-payment' }}" wire:navigate>{{ $invoice->customer_name }} · {{ $invoice->invoice_number }} · {{ $invoice->currency }} {{ number_format($invoice->balanceDue(), 2) }} still to pay</a></li>
                @empty<li class="text-sm text-zinc-500">No outstanding invoices for this client and currency.</li>@endforelse</ul>
        </details>
        <form method="GET" action="{{ route('statements') }}" x-data class="flex flex-wrap items-end gap-4">
            <flux:select name="client" label="Client" x-on:change="$el.form.requestSubmit()">
                <option value="" @selected(!$client)>All clients</option>
                @foreach($clients as $option)<option value="{{ $option->customer_email }}" @selected($client === $option->customer_email)>{{ $option->customer_name }} ({{ $option->customer_email }})</option>@endforeach
            </flux:select>
            <flux:select name="currency" label="Currency" x-on:change="$el.form.requestSubmit()">@foreach(['USD', 'ZWG'] as $option)<option value="{{ $option }}" @selected($currency === $option)>{{ $option === 'ZWG' ? 'ZiG (ZWG)' : $option }}</option>@endforeach</flux:select>
            <flux:input name="from" type="date" label="From date" :value="$from" />
            <flux:input name="to" type="date" label="To date" :value="$to" />
            <flux:button type="submit" variant="primary">View statement</flux:button>
            <flux:button type="submit" :formaction="route('statements.download')">Generate report (PDF)</flux:button>
            <flux:button type="submit" :formaction="route('statements.excel')">Generate Excel</flux:button>
        </form>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['Invoice charges' => $charged, 'Payments received' => $received, 'Credit notes' => $credited, ($balance < 0 ? 'Client credit' : 'Still to pay') => abs($balance)] as $label => $amount)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-[#10261c]"><p class="text-sm text-zinc-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $currency }} {{ number_format($amount / 100, 2) }}</p></div>
            @endforeach
        </div>
        @if($from)<p class="text-sm">Opening balance before {{ $from }}: <strong>{{ $currency }} {{ number_format($openingBalance / 100, 2) }}</strong>. The running balance includes this opening amount.</p>@endif
        <h2 class="text-xl font-semibold">Transaction details</h2>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-[#10261c]">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead><tr><th class="pb-4">Date</th><th>Client</th><th>Details</th><th class="text-right">Invoice charge<br><span class="font-normal text-zinc-500">Debit</span></th><th class="text-right">Payment received<br><span class="font-normal text-zinc-500">Credit</span></th><th class="text-right">Credit note</th><th class="text-right">Still to pay<br><span class="font-normal text-zinc-500">Running balance</span></th></tr></thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="py-4 pr-3">{{ $entry['date_label'] }}</td><td class="pr-3">{{ $entry['client'] }}</td><td class="max-w-xs pr-3"><a href="{{ route('documents.show', ['invoices', $entry['invoice']->id]) }}" class="underline" wire:navigate>{{ $entry['description'] }}</a></td><td class="text-right">{{ $entry['charged'] ? number_format($entry['charged'] / 100, 2) : '—' }}</td><td class="text-right">{{ $entry['received'] ? number_format($entry['received'] / 100, 2) : '—' }}</td><td class="text-right">{{ $entry['credited'] ? number_format($entry['credited'] / 100, 2) : '—' }}</td><td class="text-right font-semibold">{{ number_format($entry['balance'] / 100, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-zinc-500">No invoice activity for this client and currency yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
