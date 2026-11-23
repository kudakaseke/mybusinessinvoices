<x-layouts.app title="Receipts">
    <div class="mx-auto max-w-7xl space-y-7">
        <div>
            <p class="text-xs tracking-widest text-zinc-500">FINANCE</p>
            <h1 class="mt-3 text-4xl font-light">Receipts</h1>
            <p class="mt-2 text-sm text-zinc-500">Receipts are available when you record a payment on an invoice.</p>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c]">
            <table class="w-full min-w-[650px] text-left text-sm">
                <thead class="text-xs text-zinc-500"><tr><th class="pb-4">Receipt</th><th>Payment date</th><th>Client</th><th>Invoice</th><th class="text-right">Amount received</th><th></th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr class="border-t border-zinc-100 dark:border-zinc-700">
                            <td class="py-5 font-medium">RCT-{{ $payment->id }}</td>
                            <td>{{ $payment->paid_on?->format('d M Y') ?? 'Unknown' }}</td>
                            <td>{{ $payment->invoice->customer_name }}</td>
                            <td><a class="underline" href="{{ route('documents.show', ['invoices', $payment->invoice_id]) }}" wire:navigate>{{ $payment->invoice->invoice_number }}</a></td>
                            <td class="text-right">{{ $payment->invoice->currency }} {{ number_format($payment->amount, 2) }}</td>
                            <td class="pl-5"><a href="{{ route('payments.receipt', [$payment->invoice_id, $payment->id]) }}">Download PDF</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-zinc-500">No receipts yet. Record a payment on an invoice to create one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>
</x-layouts.app>
