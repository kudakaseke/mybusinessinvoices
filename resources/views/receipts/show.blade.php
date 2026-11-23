<x-layouts.app :title="'Receipt RCT-'.$payment->id">
    @php
        $invoice = $payment->invoice;
        $message = 'Receipt RCT-'.$payment->id."\n".$invoice->business_name."\nFor: ".$invoice->customer_name."\nAmount received: ".$invoice->currency.' '.number_format((float) $payment->amount, 2);
        $phone = preg_replace('/[^0-9]/', '', $invoice->customer_phone ?? '');
        $whatsappUrl = 'https://wa.me/'.($phone ?: '').'?text='.rawurlencode($message);
    @endphp
    <div class="mx-auto max-w-5xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('receipts.index') }}" class="text-sm underline" wire:navigate>Back to receipts</a>
            <div class="flex flex-wrap gap-3">
                <flux:button :href="route('payments.receipt', [$invoice->id, $payment->id])" variant="primary">Download PDF</flux:button>
                <form method="POST" action="{{ route('receipts.email', $payment->id) }}">@csrf<flux:button type="submit">Email PDF</flux:button></form>
                <flux:button :href="$whatsappUrl" target="_blank" rel="noopener noreferrer">WhatsApp</flux:button>
            </div>
        </div>
        <x-save-errors />
        <p class="text-sm text-zinc-500">Email PDF sends the receipt to {{ $invoice->customer_email }}. WhatsApp opens a message; attach the downloaded PDF before sending.</p>
        <iframe title="Receipt PDF preview" src="data:application/pdf;base64,{{ $pdfData }}#zoom=100&amp;toolbar=0&amp;navpanes=0" class="min-h-[800px] w-full rounded-xl border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
    </div>
</x-layouts.app>
