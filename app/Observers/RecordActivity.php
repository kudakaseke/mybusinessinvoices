<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class RecordActivity
{
    public function created(Model $record): void
    {
        $this->record($record, 'created', []);
    }

    public function updated(Model $record): void
    {
        $fields = ['name', 'email', 'phone', 'address', 'merchant', 'description', 'category', 'amount', 'unit_price', 'reference', 'receipt_original_name', 'paid_on', 'voided_on', 'void_reason', 'deleted_at', 'status', 'total', 'currency', 'expense_date', 'refunded_on', 'reason'];
        $changes = [];
        foreach (Arr::only($record->getChanges(), $fields) as $field => $value) {
            $changes[$field] = ['before' => $record->getRawOriginal($field), 'after' => $value];
        }
        if ($changes !== []) {
            $this->record($record, 'updated', $changes);
        }
    }

    public function deleted(Model $record): void
    {
        $this->record($record, 'voided', ['reason' => $record->getAttribute('void_reason')]);
    }

    private function record(Model $record, string $action, array $changes): void
    {
        $owner = $record->getAttribute('user_id') ?? $record->invoice?->user_id;
        if ($owner === null) {
            return;
        }
        $type = class_basename($record);
        $label = $record->getAttribute('invoice_number') ?? $record->getAttribute('quotation_number')
            ?? $record->getAttribute('number') ?? $record->getAttribute('name') ?? '#'.$record->getKey();
        ActivityLog::create([
            'user_id' => $owner, 'action' => $action, 'record_type' => $type,
            'record_id' => $record->getKey(), 'description' => $type.' '.$label.' '.$action,
            'changes' => $changes,
        ]);
    }
}
