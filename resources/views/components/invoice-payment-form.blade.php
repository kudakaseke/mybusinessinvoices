@props(['document'])
<form method="POST" action="{{ route('invoices.payment', $document->id) }}" class="space-y-4 text-left">
    @csrf
    @method('PATCH')
    <input type="hidden" name="status" value="paid" />
    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}" />
    <p class="text-sm">Still to pay: <strong>{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</strong></p>
    <flux:input name="amount" type="number" step="0.01" min="0.01" :max="$document->balanceDue()" :value="old('amount')" :label="'Amount received ('.$document->currency.')'" placeholder="Enter the amount paid" required />
    <flux:input name="paid_on" type="date" :value="old('paid_on', today('Africa/Johannesburg')->toDateString())" :min="$document->issue_date->toDateString()" :max="today('Africa/Johannesburg')->toDateString()" label="Payment date" required />
    <flux:input name="reference" :value="old('reference')" label="Reference or note (optional)" maxlength="255" placeholder="e.g. Bank transfer or deposit" />
    <p class="text-xs text-zinc-500">Record only this payment. You can add the rest later.</p>
    <flux:button type="submit" variant="primary">Save payment</flux:button>
</form>
