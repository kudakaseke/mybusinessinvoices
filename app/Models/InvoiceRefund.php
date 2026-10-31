<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceRefund extends Model
{
    use HasFactory;

    protected $fillable = ['amount', 'refunded_on', 'reason', 'submission_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
