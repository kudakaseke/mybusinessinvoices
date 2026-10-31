<x-layouts.app :title="$kind==='invoices' ? $document->invoice_number : $document->quotation_number">
<div class="mx-auto max-w-5xl">
@if(in_array(session('message'), ['Document saved.', 'Payment status updated.'], true))
<div x-data="{ open: true }" x-show="open" x-on:keydown.escape.window="open=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-5 print:hidden" role="dialog" aria-modal="true" aria-labelledby="document-success-heading" x-trap.inert.noscroll="open">
    <section class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-8 text-center text-[#13291f] shadow-xl">
        <button type="button" x-on:click="open=false" aria-label="Close popup" title="Close popup" class="absolute top-3 right-3 flex size-10 items-center justify-center rounded-full text-2xl text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">&times;</button>
        <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-800" aria-hidden="true">&#10003;</div>
        <h2 id="document-success-heading" class="text-2xl font-semibold">{{ $kind==='invoices'?'Invoice':'Quotation' }} saved successfully!</h2>
        @if($kind === 'invoices' && session('message') === 'Document saved.')
        <p class="mt-3 mb-6 text-sm text-zinc-500">Have you received payment for this invoice?</p>
        <div x-data="{ received: false }">
            <div x-show="!received" class="flex flex-wrap justify-center gap-3">
                <flux:button type="button" x-on:click="received = true" variant="primary">Payment received</flux:button>
                <form method="POST" action="{{ route('invoices.payment', $document->id) }}">
                    @csrf
                    @method('PATCH')
                    <flux:button type="submit" name="status" value="outstanding">Not yet paid</flux:button>
                </form>
            </div>
            <div x-show="received" x-cloak>
                <button type="button" x-on:click="received=false" class="mb-4 text-sm underline">&larr; Back to payment choices</button>
                <x-invoice-payment-form :document="$document" />
            </div>
        </div>
        @else
        <p class="mt-3 mb-6 text-sm text-zinc-500">Your document is ready. Choose what to do next.</p>
        <x-document-actions :document="$document" :kind="$kind" />
        <p class="mt-5 text-xs leading-5 text-zinc-500">Email PDF sends the document directly to your client. Email draft and WhatsApp open drafts where you can attach the downloaded PDF.</p>
        @endif
        <div class="mt-6 flex flex-wrap items-center justify-center gap-4">
            <flux:button :href="route('documents.index', $kind)" wire:navigate>Back to {{ $kind }}</flux:button>
            <flux:button type="button" x-on:click="open=false">View document</flux:button>
        </div>
    </section>
</div>
@endif
<div class="mb-7 flex flex-wrap justify-between gap-4 print:hidden"><a href="{{ route('documents.index',$kind) }}" class="text-sm" wire:navigate>← {{ ucfirst($kind) }}</a><div class="flex gap-3"><x-document-actions :document="$document" :kind="$kind" /></div></div><div class="print:hidden"><x-save-errors /></div>
@if($kind === 'invoices' && (float) $document->balanceDue() > 0)
<details id="record-payment" @if(request()->boolean('record_payment') || $errors->any()) open @endif class="mb-6 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-[#10261c] print:hidden">
    <summary class="cursor-pointer font-semibold">Record a payment</summary>
    <div class="mt-4 max-w-lg"><x-invoice-payment-form :document="$document" /></div>
</details>
@endif
@if($kind === 'quotations')
    <form method="POST" action="{{ route('quotations.convert', $document->id) }}" class="mb-6 print:hidden">@csrf<flux:button type="submit" variant="primary">Convert to invoice</flux:button></form>
@elseif($document->isOverdue())
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-red-50 p-5 text-red-900 print:hidden">
        <p><strong>Overdue</strong> — {{ $document->currency }} {{ $document->balanceDue() }} is still unpaid.</p>
        <form method="POST" action="{{ route('invoices.remind', $document->id) }}">@csrf<flux:button type="submit">Send payment reminder</flux:button></form>
    </div>
@endif
<article class="uppercase invoice-paper min-h-[1000px] rounded-xl border border-zinc-200 bg-white p-6 text-[#151915] shadow-sm md:p-12">
<div class="flex items-start justify-between gap-8"><div><h1 class="text-3xl font-semibold">{{ $kind==='invoices'?'Invoice':'Quotation' }}</h1><dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs"><dt class="font-semibold">{{ $kind==='invoices'?'Invoice':'Quotation' }} number</dt><dd>{{ $kind==='invoices'?$document->invoice_number:$document->quotation_number }}</dd><dt>Date of issue</dt><dd>{{ $document->issue_date->format('F j, Y') }}</dd><dt>{{ $kind==='invoices'?'Date due':'Valid until' }}</dt><dd>{{ $document->due_date->format('F j, Y') }}</dd></dl></div><span class="max-w-56 text-right text-2xl font-light text-[#103b26]">{{ $document->business_name }}</span></div>
<div class="mt-8 grid grid-cols-2 gap-8 text-sm leading-6"><section><h2 class="font-semibold">{{ $document->business_name }}</h2><p class="whitespace-pre-line">{{ $document->business_address }}</p><p>{{ $document->business_email }}</p><p>{{ $document->business_phone }}</p></section><section><h2 class="font-semibold">Bill to</h2><p><strong class="font-bold">CUSTOMER NAME:</strong> {{ $document->customer_name }}</p><p class="whitespace-pre-line"><strong class="font-bold">CUSTOMER PHYSICAL ADDRESS:</strong> {{ $document->customer_address }}</p><p><strong class="font-bold">PHONE NUMBER:</strong> {{ $document->customer_phone }}</p><p><strong class="font-bold">CUSTOMER EMAIL ADDRESS:</strong> <span class="normal-case">{{ $document->customer_email }}</span></p></section></div>
<h2 class="mt-10 text-xl font-semibold">{{ $document->currency }} {{ number_format($kind === 'invoices' && $document->status !== 'paid' ? $document->balanceDue() : $document->total,2) }} {{ $kind==='invoices' ? ($document->status==='paid' ? 'paid' : 'due '.$document->due_date->format('F j, Y')) : 'quoted' }}</h2>
<div class="mt-12 overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr class="border-b border-black"><th class="pb-2 font-normal">Description</th><th class="pb-2 text-right font-normal">Qty</th><th class="pb-2 text-right font-normal">Unit price</th><th class="pb-2 text-right font-normal">Amount</th></tr></thead><tbody>@foreach($document->items as $item)<tr><td class="py-3 pr-4">{{ $item->description }}</td><td class="text-right">{{ number_format($item->quantity,2) }}</td><td class="text-right">{{ number_format($item->unit_price,2) }}</td><td class="text-right">{{ number_format($item->amount,2) }}</td></tr>@endforeach</tbody></table></div>
<dl class="mt-7 ml-auto max-w-sm space-y-2 text-xs"><div class="flex justify-between border-t border-zinc-200 pt-2"><dt>Subtotal</dt><dd>{{ number_format($document->subtotal,2) }}</dd></div><div class="flex justify-between border-t border-zinc-200 pt-2"><dt>Discount ({{ number_format($document->discount_rate,2) }}%)</dt><dd>−{{ number_format($document->discount_amount,2) }}</dd></div><div class="flex justify-between border-t border-zinc-200 pt-2"><dt>VAT ({{ number_format($document->vat_rate,2) }}%)</dt><dd>{{ number_format($document->vat_amount,2) }}</dd></div><div class="flex justify-between border-t border-zinc-200 pt-2"><dt>Total</dt><dd>{{ number_format($document->total,2) }}</dd></div>@if($kind==='invoices')<div class="flex justify-between border-t border-zinc-200 pt-2"><dt>Credit notes</dt><dd>{{ number_format($document->creditedAmount(),2) }} {{ $document->currency }}</dd></div>@endif<div class="flex justify-between border-t border-zinc-200 pt-2 font-semibold"><dt>{{ $kind==='invoices'?'Amount due':'Quotation total' }}</dt><dd>{{ number_format($kind==='invoices' ? $document->balanceDue() : $document->total,2) }} {{ $document->currency }}</dd></div></dl>
@if($document->payment_account_name || $document->payment_account_number || $document->payment_bank_name)<section class="mt-12 text-xs leading-6"><h2 class="font-semibold">Payment details</h2>@foreach(['payment_bank_name'=>'Bank','payment_account_name'=>'Account name','payment_account_number'=>'Account number','payment_account_currency'=>'Currency'] as $field=>$label)@if($document->$field)<p>{{ $label }}: {{ $document->$field }}</p>@endif @endforeach</section>@endif
@if($kind==='quotations' && $document->notes)<section class="mt-8 text-xs"><h2 class="font-semibold">Notes</h2><p class="mt-2 whitespace-pre-line">{{ $document->notes }}</p></section>@endif
@if($document->prepared_by)<p class="mt-10 text-xs text-zinc-500">Prepared by {{ $document->prepared_by }}</p>@endif
</article>
@if($kind === 'invoices')
<section class="mt-6 space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c] print:hidden">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Payment history</h2><a class="text-sm underline" href="{{ route('statements', ['client' => $document->customer_email, 'currency' => $document->currency]) }}" wire:navigate>View client statement</a></div>
    <p>Received: <strong>{{ $document->currency }} {{ number_format($document->paidAmount(), 2) }}</strong> &middot; Still to pay: <strong>{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</strong></p>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="pb-3">Payment date</th><th>Reference</th><th class="text-right">Amount received ({{ $document->currency }})</th></tr></thead><tbody>
        @forelse($document->paymentHistory as $payment)<tr class="border-t border-zinc-200 dark:border-zinc-700">
            <td class="py-3">{{ $payment->paid_on?->format('d M Y') ?? 'Original date unknown' }}</td>
            <td class="py-3">{{ $payment->reference ?? '—' }}
                @if($payment->trashed())
                    <p class="mt-2 text-sm text-red-600">Reversed on {{ $payment->voided_on?->format('d M Y') }}: {{ $payment->void_reason }}</p>
                @else
                    <a class="ml-3 underline" href="{{ route('payments.receipt', [$document->id, $payment->id]) }}">Download receipt</a>
                    <details class="mt-2"><summary class="cursor-pointer text-xs text-red-600">Correct this payment</summary>
                        <form method="POST" action="{{ route('payments.void', [$document->id, $payment->id]) }}" class="mt-3 grid max-w-sm gap-3">@csrf
                            <p class="text-xs text-zinc-500">Reverse the mistaken entry, then record the correct payment. The original is kept for your records.</p>
                            <flux:input name="voided_on" type="date" label="Correction date" :value="today('Africa/Johannesburg')->toDateString()" required />
                            <flux:input name="reason" label="Reason for correction" maxlength="255" required />
                            <flux:button type="submit" variant="danger">Reverse payment</flux:button>
                        </form>
                    </details>
                @endif
            </td>
            <td class="text-right {{ $payment->trashed() ? 'text-zinc-500 line-through' : '' }}">{{ number_format($payment->amount, 2) }}</td>
        </tr>@empty<tr><td colspan="3" class="py-4 text-zinc-500">No payments recorded yet.</td></tr>@endforelse
    </tbody></table></div>
    @if((float) $document->balanceDue() <= 0)
        <p class="font-medium text-emerald-700 dark:text-emerald-300">{{ (float) $document->creditedAmount() > 0 ? 'Settled with credit notes. Nothing left to pay.' : 'Fully paid. Nothing left to pay.' }}</p>
    @endif
</section>
<section class="mt-6 space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c] print:hidden">
    <h2 class="text-xl font-semibold">Credit notes</h2>
    <p>Credits issued: <strong>{{ $document->currency }} {{ number_format($document->creditedAmount(), 2) }}</strong></p>
    @if((float) $document->customerCredit() > 0)<p class="rounded-lg bg-amber-50 p-4 text-amber-900">Client credit: <strong>{{ $document->currency }} {{ number_format($document->customerCredit(), 2) }}</strong>. This amount is available to refund. @if((float) $document->refundedAmount() === 0.0)No cash refund has been recorded.@endif</p>@endif
    <ul class="space-y-3">@foreach($document->creditNotes as $note)<li><a class="underline" href="{{ route('credit-notes.download', $note->id) }}">{{ $note->number }} · {{ $note->credited_on->format('d M Y') }} · {{ $document->currency }} {{ number_format($note->amount, 2) }} · Download PDF</a><p class="text-sm text-zinc-500">{{ $note->reason }}</p></li>@endforeach</ul>
    @if((float) $document->creditRemaining() > 0)
    <details @if($errors->any()) open @endif><summary class="cursor-pointer font-medium">Issue a credit note</summary>
        <x-credit-note-form :document="$document" />
    </details>
    @endif
</section>
<section class="mt-6 space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-[#10261c] print:hidden">
    <h2 class="text-xl font-semibold">Cash refunds</h2>
    <p>Already refunded: <strong>{{ $document->currency }} {{ $document->refundedAmount() }}</strong></p>
    <ul class="space-y-2">@foreach($document->refunds as $refund)<li>{{ $refund->refunded_on->format('d M Y') }} · {{ $document->currency }} {{ $refund->amount }} · {{ $refund->reason }}</li>@endforeach</ul>
    @if((float) $document->customerCredit() > 0)
    <details><summary class="cursor-pointer font-semibold">Record a cash refund</summary>
        <form method="POST" action="{{ route('invoices.refunds', $document->id) }}" class="mt-4 grid max-w-lg gap-4">@csrf
            <input type="hidden" name="submission_key" value="{{ Illuminate\Support\Str::uuid() }}" />
            <p class="text-sm text-zinc-500">Only record money actually returned to the client. A credit note alone does not return cash.</p>
            <flux:input type="number" name="amount" label="Amount returned" min="0.01" step="0.01" :max="$document->customerCredit()" required />
            <flux:input type="date" name="refunded_on" label="Refund date" :value="today('Africa/Johannesburg')->toDateString()" required />
            <flux:input name="reason" label="Reason or payment reference" maxlength="255" required />
            <flux:button type="submit" variant="primary">Record refund</flux:button>
        </form>
    </details>
    @endif
</section>
@endif
</div></x-layouts.app>
