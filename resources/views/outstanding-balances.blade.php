<x-layouts.app title="Outstanding balances">
    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <p class="text-xs tracking-widest text-zinc-500">FINANCE</p>
            <h1 class="mt-2 text-3xl font-light">Outstanding balances</h1>
            <p class="mt-2 text-sm text-zinc-500">Customers with unpaid invoice balances, including amounts not yet overdue.</p>
        </div>
        <x-save-errors />
        @if($customers->isNotEmpty())
            <details class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-[#10261c]" @if($errors->has('invoice_id')) open @endif>
                <summary class="inline-flex cursor-pointer rounded-lg bg-[#103b26] px-4 py-2 text-sm font-semibold text-white hover:bg-[#174d32]">Record Payment</summary>
                <form method="GET" action="{{ route('outstanding-balances.record-payment') }}" class="mt-4 flex max-w-2xl flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <flux:select name="invoice_id" label="Customer and outstanding invoice" required>
                            <option value="">Select a customer and invoice</option>
                            @foreach($customers as $customer)
                                @foreach($customer['invoices'] as $entry)
                                    <option value="{{ $entry['invoice']->id }}">{{ $customer['name'] }} ? {{ $entry['invoice']->invoice_number }} ? {{ $customer['currency'] }} {{ number_format($entry['balance'] / 100, 2) }} due</option>
                                @endforeach
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button type="submit" variant="primary">Continue to payment</flux:button>
                </form>
            </details>
        @endif
        @if($totals->isNotEmpty())
            <div class="flex flex-wrap gap-3">
                @foreach($totals as $currency => $total)
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-[#10261c]">
                        <p class="text-sm text-zinc-500">Total outstanding ({{ $currency }})</p>
                        <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format($total / 100, 2) }}</p>
                    </div>
                @endforeach
            </div>
        @endif
        @forelse($customers as $customer)
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-[#10261c]">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $customer['name'] }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $customer['email'] }}@if($customer['phone']) &middot; {{ $customer['phone'] }}@endif</p>
                    </div>
                    <div><p class="text-sm text-zinc-500">Outstanding balance</p><p class="mt-1 text-lg font-semibold">{{ $customer['currency'] }} {{ number_format($customer['balance'] / 100, 2) }}</p></div>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[550px] text-left text-sm">
                        <thead class="text-xs text-zinc-500"><tr><th class="pb-2">Invoice</th><th>Due date</th><th>Status</th><th class="text-right">Amount due</th><th></th></tr></thead>
                        <tbody>
                            @foreach($customer['invoices'] as $entry)
                                @php($invoice = $entry['invoice'])
                                <tr class="border-t border-zinc-100 dark:border-zinc-700">
                                    <td class="py-2 font-medium">{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->due_date->format('d M Y') }}</td>
                                    <td>{{ $entry['overdue'] ? 'Overdue' : ($invoice->due_date->isSameDay(today('Africa/Johannesburg')) ? 'Due today' : 'Not yet due') }}</td>
                                    <td class="text-right">{{ $invoice->currency }} {{ number_format($entry['balance'] / 100, 2) }}</td>
                                    <td class="pl-5"><a class="underline" href="{{ route('documents.show', ['invoices', $invoice->id]) }}" wire:navigate>View invoice</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-zinc-200 bg-white p-12 text-center text-zinc-500 dark:border-zinc-700 dark:bg-[#10261c]">No outstanding balances yet.</div>
        @endforelse
    </div>
</x-layouts.app>
