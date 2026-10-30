<?php

namespace App\Models;

use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    protected $fillable = ['number', 'amount', 'credited_on', 'reason', 'submission_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'credited_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
