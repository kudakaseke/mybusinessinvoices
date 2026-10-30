<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'quotation_number', 'issue_date', 'due_date', 'business_name', 'business_address', 'business_email', 'business_phone', 'prepared_by', 'payment_account_name', 'payment_account_number', 'payment_account_currency', 'payment_bank_name', 'customer_name', 'customer_address', 'customer_phone', 'customer_email', 'currency', 'subtotal', 'vat_rate', 'vat_amount', 'discount_rate', 'discount_amount', 'total', 'notes'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'vat_amount' => 'decimal:2', 'discount_amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
