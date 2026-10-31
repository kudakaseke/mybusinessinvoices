<?php

namespace App\Models;

use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    /** @use HasFactory<InvoicePaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['amount', 'paid_on', 'reference', 'submission_key', 'void_reason', 'voided_on'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'voided_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
