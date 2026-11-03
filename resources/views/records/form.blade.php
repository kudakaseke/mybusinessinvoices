<x-layouts.app :title="ucfirst($kind)"><div class="mx-auto max-w-3xl"><a href="{{ route('records.index',$kind) }}" class="text-sm text-zinc-500" wire:navigate>← {{ ucfirst($kind) }}</a><h1 class="mt-6 mb-8 text-4xl font-light">{{ $entry->exists ? 'Edit' : 'Add' }} {{ rtrim($kind,'s') }}</h1><x-save-errors /><form method="POST" enctype="multipart/form-data" action="{{ $entry->exists ? route('records.update',[$kind,$entry->id]) : route('records.store',$kind) }}" x-data="{ saving: false }" x-on:submit="saving=true" x-on:pageshow.window="saving=false" class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-8 dark:border-zinc-700 dark:bg-[#10261c]">@csrf @if($entry->exists)@method('PUT')@endif
@if($kind==='clients')<flux:input name="name" label="Client name" :value="old('name',$entry->name)" required /><flux:input type="email" name="email" label="Email" :value="old('email',$entry->email)" required /><flux:input name="phone" label="Phone" :value="old('phone',$entry->phone)" /><flux:textarea name="address" label="Address">{{ old('address',$entry->address) }}</flux:textarea>
@elseif($kind==='products')<flux:input name="name" label="Product name" :value="old('name',$entry->name)" required /><flux:textarea name="description" label="Description">{{ old('description',$entry->description) }}</flux:textarea><flux:input name="unit_price" type="number" min="0" step="0.01" label="Unit price" :value="old('unit_price',$entry->unit_price ?? 0)" required />
@else<flux:input name="merchant" label="Expense Name" placeholder="e.g. TotalEnergies" :value="old('merchant',$entry->merchant)" required /><flux:input name="description" label="Description" placeholder="e.g. Fuel for a client visit" :value="old('description',$entry->description)" required /><flux:input name="category" label="Category" list="expense-category-suggestions" placeholder="Choose or type a category" description="Pick a suggestion or enter your own category." :value="old('category',$entry->category)" required /><datalist id="expense-category-suggestions">
    <option value="Transport"></option>
    <option value="Office supplies"></option>
    <option value="Meals"></option>
    <option value="Rent"></option>
    <option value="Utilities"></option>
    <option value="Internet and phone"></option>
    <option value="Travel"></option>
    <option value="Marketing"></option>
    <option value="Software and subscriptions"></option>
    <option value="Repairs and maintenance"></option>
    <option value="Professional services"></option>
    <option value="Bank fees"></option>
    <option value="Other"></option>
</datalist>
<flux:input name="expense_date" type="date" label="Expense date" description="Choose the date shown on your receipt." :value="old('expense_date',$entry->expense_date?->format('Y-m-d'))" required /><flux:input name="amount" type="number" min="0.01" step="0.01" label="Amount" placeholder="e.g. 25.00" :value="old('amount',$entry->amount)" required /><flux:input name="receipt" type="file" label="Receipt (PDF, JPG, PNG · max 10 MB)" accept=".pdf,.jpg,.jpeg,.png" />@if($entry->receipt_original_name)<p class="text-xs text-zinc-500">Recorded receipt: {{ $entry->receipt_original_name }}. Leave blank to retain it.</p>@endif
@endif
@if($kind!=='clients')<flux:select name="currency" label="Currency">@foreach(['USD','ZWG'] as $currency)<option value="{{ $currency }}" @selected(old('currency',$entry->currency)===$currency)>{{ $currency }}</option>@endforeach</flux:select>@endif
<flux:button type="submit" variant="primary" x-bind:disabled="saving"><span x-show="!saving">Save {{ rtrim($kind,'s') }}</span><span x-show="saving" x-cloak>Saving…</span></flux:button></form></div></x-layouts.app>
