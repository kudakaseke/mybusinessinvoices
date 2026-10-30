@props(['document'])
<form method="POST" action="{{ route('credit-notes.store', $document->id) }}" class="mt-4 max-w-lg space-y-4">
@csrf
<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}" />
<p class="text-sm">Amount available to credit: {{ $document->currency }} {{ number_format($document->creditRemaining(), 2) }}</p>
<flux:input name="amount" label="Credit amount (including tax)" type="number" min="0.01" step="0.01" :max="$document->creditRemaining()" :value="old('amount')" required />
<flux:input name="credited_on" label="Credit note date" type="date" :min="$document->issue_date->toDateString()" :max="today('Africa/Johannesburg')->toDateString()" :value="old('credited_on', today('Africa/Johannesburg')->toDateString())" required />
<flux:textarea name="reason" label="Reason" placeholder="e.g. Returned goods or invoice correction" maxlength="255" required>{{ old('reason') }}</flux:textarea>
<p class="text-xs text-zinc-500">The original invoice and payments stay on record. An issued credit note cannot be edited.</p>
<flux:button type="submit" variant="primary">Issue credit note</flux:button>
</form>
