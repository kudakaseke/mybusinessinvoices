<x-layouts.app :title="$kind === 'invoices' ? 'Invoice' : 'Quotation'">
@php
$number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
$initial = [
    'draftKey' => $draftKey,
    'draftUrl' => route('drafts.save', $kind),
    'items' => old('items',$items),
    'clients' => $clients->map(fn($client) => $client->only(['id','name','email','address','phone']))->values(),
    'products' => $products->map(fn($product) => $product->only(['id','name','description','unit_price','currency']))->values(),
    'customer_name' => old('customer_name',$document->customer_name ?? ''),
    'customer_email' => old('customer_email',$document->customer_email ?? ''),
    'customer_address' => old('customer_address',$document->customer_address ?? ''),
    'customer_phone' => old('customer_phone',$document->customer_phone ?? ''),
    'currency' => old('currency',$document->currency),
    'business_name' => old('business_name',$document->business_name ?? ''),
    'due_date' => old('due_date',$document->due_date?->format('Y-m-d') ?? ''),
    'vat_rate' => old('vat_rate',$document->vat_rate),
    'discount_rate' => old('discount_rate',$document->discount_rate),
];
@endphp

<div class="mx-auto max-w-6xl">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('documents.index',$kind) }}" class="text-sm" wire:navigate>&larr; {{ ucfirst($kind) }}</a>
        <span class="text-xs text-zinc-500">Click the document fields to enter your details.</span>
    </div>
    <x-save-errors />
    <form method="POST" action="{{ $document->exists ? route('documents.update',[$kind,$document->id]) : route('documents.review',$kind) }}" x-data="documentEditor({{ Illuminate\Support\Js::from($initial) }})" x-on:input="queueDraft()" x-on:change="queueDraft()" x-on:submit.prevent="submitReview($event)" x-on:click.window.capture="guardLink($event)" x-on:beforeunload.window="if (!submitting && (dirty || saving)) { $event.preventDefault(); $event.returnValue = '' }">
        @csrf
        <input type="hidden" name="draft_key" value="{{ $draftKey }}" />
        @if($kind === 'invoices' && old('quotation_id', $draftPayload['quotation_id'] ?? null))<input type="hidden" name="quotation_id" value="{{ old('quotation_id', $draftPayload['quotation_id'] ?? null) }}" />@endif
        @if($document->exists) @method('PUT') @endif
        <div x-show="!reviewing" class="mb-5 flex flex-wrap items-end gap-4 print:hidden">
            <div class="flex items-center gap-3"><flux:button type="button" x-on:click="saveDraft()" x-bind:disabled="saving || submitting">Save draft</flux:button><span role="status" class="text-xs text-zinc-500" x-text="draftStatus">Unsaved draft</span><a class="text-xs underline" href="{{ route('drafts.index') }}" wire:navigate>Saved drafts</a></div>
            <div><label for="document-currency" class="mb-1 block text-xs">Currency</label><select id="document-currency" name="currency" x-model="currency" x-on:change="items.forEach(item => item.product_id = '')" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-black"><option value="USD">USD</option><option value="ZWG">ZWG</option></select></div>
            @if($kind==='invoices')
                <input type="hidden" name="status" value="outstanding" />
            @endif
            <div class="ml-auto"><flux:button type="submit" variant="primary">Review {{ $kind==='invoices'?'invoice':'quotation' }}</flux:button></div>
        </div>
        <article x-bind:inert="reviewing" class="document-editor invoice-paper border border-zinc-200 bg-white p-5 text-black shadow-sm sm:p-10 lg:p-12">
            <div class="flex items-start justify-between gap-6">
                <h1 class="text-3xl font-semibold">{{ $kind==='invoices'?'Invoice':'Quotation' }}</h1>
                <span class="max-w-[45%] text-right text-3xl font-light tracking-tight text-[#103b26] sm:text-5xl" x-text="business_name || 'Your business'"></span>
            </div>
            <dl class="mt-8 grid max-w-xl grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-1 text-sm sm:text-base">
                <dt class="font-semibold"><label for="document-number">{{ $kind==='invoices'?'Invoice':'Quotation' }} number</label></dt>
                <dd><input id="document-number" class="document-input font-semibold" name="{{ $number }}" value="{{ old($number,$document->$number) }}" placeholder="Document number" required maxlength="255" /></dd>
                <dt><label for="issue-date">Date of issue</label></dt><dd><input id="issue-date" class="document-input" name="issue_date" type="date" value="{{ old('issue_date',$document->issue_date?->format('Y-m-d')) }}" required /></dd>
                <dt><label for="due-date">{{ $kind==='invoices'?'Date due':'Valid until' }}</label></dt><dd><input id="due-date" class="document-input" name="due_date" type="date" x-model="due_date" required /></dd>
            </dl>
            <div class="mt-10 grid gap-8 text-sm leading-6 sm:grid-cols-[.8fr_1.2fr] sm:text-base">
                <section class="space-y-1">
                    <input class="document-input font-semibold" name="business_name" aria-label="Business name" x-model="business_name" placeholder="YOUR BUSINESS NAME" required maxlength="255" />
                    <textarea class="document-input resize-y" name="business_address" aria-label="Business address" placeholder="Street address&#10;City, postal code&#10;Country" rows="3">{{ old('business_address',$document->business_address) }}</textarea>
                    <input class="document-input" name="business_email" type="email" aria-label="Business email" value="{{ old('business_email',$document->business_email) }}" placeholder="business@example.com" required />
                    <input class="document-input" name="business_phone" aria-label="Business phone" value="{{ old('business_phone',$document->business_phone) }}" placeholder="Phone (optional)" />
                </section>
                <section class="space-y-1">
                    <div class="mb-2 flex flex-wrap items-center gap-4"><h2 class="font-semibold">Bill to</h2><select aria-label="Choose a saved client" class="max-w-56 rounded border border-zinc-200 bg-white px-2 py-1 text-xs text-zinc-500" x-on:change="selectClient($event.target.value)"><option value="">Choose client (optional)</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach</select></div>
                    <input class="document-input" name="customer_name" aria-label="Client name" x-model="customer_name" placeholder="CLIENT NAME" required />
                    <textarea class="document-input resize-y" name="customer_address" aria-label="Client address" x-model="customer_address" placeholder="Client address" rows="2"></textarea>
                    <input class="document-input" name="customer_email" type="email" aria-label="Client email" x-model="customer_email" placeholder="client@example.com" required />
                    <input class="document-input" name="customer_phone" aria-label="Client phone" x-model="customer_phone" placeholder="Phone (optional)" />
                </section>
            </div>
            <h2 class="mt-10 text-xl font-semibold sm:text-2xl"><span x-text="(currency==='USD'?'$':'')+money(total)+' '+currency"></span> <span x-text="due_date ? '{{ $kind==='invoices'?'due':'valid until' }} '+new Date(due_date+'T12:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}) : ''"></span></h2>
            <div class="mt-16 overflow-x-auto">
                <table class="w-full min-w-[520px] table-fixed text-left text-sm sm:text-base">
                    <thead><tr class="border-b-2 border-black text-xs sm:text-sm"><th class="w-[61%] pb-2 font-normal">Description</th><th class="w-[10%] pb-2 text-right font-normal">Qty</th><th class="w-[15%] pb-2 text-right font-normal">Unit price</th><th class="w-[14%] pb-2 text-right font-normal">Amount</th></tr></thead>
                    <tbody><template x-for="(item,index) in items" :key="index"><tr class="align-top">
                        <td class="py-2 pr-5"><input class="document-input" :name="'items['+index+'][description]'" x-model="item.description" :aria-label="'Description for item '+(index+1)" placeholder="Product or service description" required maxlength="255" /><div class="mt-1 flex flex-wrap items-center gap-3"><select class="max-w-60 rounded border border-zinc-200 bg-white px-2 py-1 text-xs text-zinc-500" :name="'items['+index+'][product_id]'" x-model="item.product_id" x-on:change="selectProduct(item)" :aria-label="'Saved product for item '+(index+1)"><option value="">Custom item</option><template x-for="product in products.filter(p=>p.currency===currency)" :key="product.id"><option :value="product.id" x-text="product.name"></option></template></select><button type="button" class="text-xs text-red-700" x-show="items.length>1" x-on:click="items.splice(index,1)">Remove</button></div></td>
                        <td class="py-2"><input class="document-input text-right" :name="'items['+index+'][quantity]'" type="number" step="0.01" min="0.01" x-model="item.quantity" :aria-label="'Quantity for item '+(index+1)" required /></td>
                        <td class="py-2"><input class="document-input text-right" :name="'items['+index+'][unit_price]'" type="number" step="0.01" min="0" x-model="item.unit_price" :aria-label="'Unit price for item '+(index+1)" required /></td>
                        <td class="py-3 text-right" x-text="(currency==='USD'?'$':'')+money(lineAmount(item))"></td>
                    </tr></template></tbody>
                </table>
            </div>
            <button type="button" class="mt-4 text-xs text-[#103b26] underline" x-on:click="items.push({product_id:'',description:'',quantity:'1',unit_price:'0'})">+ Add line item</button>
            <dl class="mt-8 ml-auto w-full text-sm sm:w-1/2 sm:text-base">
                <div class="flex justify-between gap-4 border-t border-zinc-200 py-1"><dt>Subtotal</dt><dd x-text="(currency==='USD'?'$':'')+money(subtotal)"></dd></div>
                <div class="flex items-center justify-between gap-4 border-t border-zinc-200 py-1"><dt><label for="discount-rate">Discount (</label><input id="discount-rate" class="document-input inline-block! w-14! text-right" name="discount_rate" type="number" step="0.01" min="0" max="100" x-model="discount_rate" required />% off)</dt><dd class="shrink-0" x-text="'-'+(currency==='USD'?'$':'')+money(discount)"></dd></div>
                <div class="flex items-center justify-between gap-4 border-t border-zinc-200 py-1"><dt><label for="vat-rate">VAT (</label><input id="vat-rate" class="document-input inline-block! w-14! text-right" name="vat_rate" type="number" step="0.01" min="0" max="100" x-model="vat_rate" required />%)</dt><dd class="shrink-0" x-text="(currency==='USD'?'$':'')+money(vat)"></dd></div>
                <div class="flex justify-between gap-4 border-t border-zinc-200 py-1"><dt>Total</dt><dd x-text="(currency==='USD'?'$':'')+money(total)"></dd></div>
                <div class="flex justify-between gap-4 border-t border-zinc-200 py-1 font-semibold"><dt>{{ $kind==='invoices'?'Amount due':'Quotation total' }}</dt><dd x-text="(currency==='USD'?'$':'')+money(total)+' '+currency"></dd></div>
            </dl>
        </article>
        <section x-bind:inert="reviewing" class="mt-6 flex flex-wrap items-center gap-4 rounded-xl border border-zinc-200 bg-white p-5 text-black">
            <label for="prepared-by" class="text-sm font-medium">Prepared by</label>
            <input id="prepared-by" name="prepared_by" required value="{{ old('prepared_by',$draftPayload['prepared_by'] ?? ($document->exists ? $document->prepared_by : '')) }}" placeholder="Name and surname" autocomplete="name" maxlength="255" class="min-w-0 flex-1 border-0 border-b border-zinc-400 bg-transparent px-2 py-2 text-sm focus:outline-emerald-700" />
            <span class="text-xs text-zinc-500">(name and surname)</span>
        </section>
        <details x-bind:open="reviewing" x-bind:inert="reviewing" class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 text-black">
            <summary class="cursor-pointer text-sm font-medium">Payment details (Optional)</summary>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach(['payment_account_name'=>'Account name','payment_account_number'=>'Account number','payment_bank_name'=>'Bank name'] as $field=>$label)<flux:input :name="$field" :label="$label" :value="old($field,$document->$field)" />@endforeach
                <flux:select name="payment_account_currency" label="Account currency"><option value="">Not specified</option>@foreach(['USD','ZWG'] as $accountCurrency)<option value="{{ $accountCurrency }}" @selected(old('payment_account_currency',$document->payment_account_currency)===$accountCurrency)>{{ $accountCurrency }}</option>@endforeach</flux:select>
            </div>
        </details>
        @if($kind==='quotations')<div x-bind:inert="reviewing" class="mt-6"><flux:textarea name="notes" label="Notes (Optional)">{{ old('notes',$document->notes) }}</flux:textarea></div>@endif
        <div class="mt-6 flex justify-end gap-3">
            <flux:button type="button" x-show="reviewing" x-cloak x-on:click="reviewing=false">Back to edit</flux:button>
            <flux:button type="submit" variant="primary"><span x-text="reviewing ? 'Save' : 'Review'">Review</span></flux:button>
        </div>
    </form>
</div>
</x-layouts.app>
