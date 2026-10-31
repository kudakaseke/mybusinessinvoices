<?php

namespace App\Models;

use App\DocumentTotals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'invoice_number', 'issue_date', 'due_date', 'business_name', 'business_address', 'business_email', 'business_phone', 'prepared_by', 'payment_account_name', 'payment_account_number', 'payment_account_currency', 'payment_bank_name', 'customer_name', 'customer_address', 'customer_phone', 'customer_email', 'currency', 'subtotal', 'vat_rate', 'vat_amount', 'discount_rate', 'discount_amount', 'total', 'status'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'vat_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'last_reminded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function paidAmount(): string
    {
        if ($this->status === 'paid' && ($this->relationLoaded('paymentHistory') ? $this->paymentHistory->isEmpty() : ! $this->payments()->withTrashed()->exists())) {
            return (string) $this->total;
        }

        $amount = $this->relationLoaded('payments')
            ? $this->payments->sum(fn (InvoicePayment $payment): int => DocumentTotals::scaled($payment->amount))
            : DocumentTotals::scaled($this->payments()->sum('amount'));

        return DocumentTotals::decimal($amount);
    }

    public function balanceDue(): string
    {
        return DocumentTotals::decimal(max(0, DocumentTotals::scaled($this->total) - DocumentTotals::scaled($this->paidAmount()) - DocumentTotals::scaled($this->creditedAmount()) + DocumentTotals::scaled($this->refundedAmount())));
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('credited_on')->orderBy('id');
    }

    public function creditedAmount(): string
    {
        $amount = $this->relationLoaded('creditNotes')
            ? $this->creditNotes->sum(fn (CreditNote $note): int => DocumentTotals::scaled($note->amount))
            : DocumentTotals::scaled($this->creditNotes()->sum('amount'));

        return DocumentTotals::decimal($amount);
    }

    public function creditRemaining(): string
    {
        return DocumentTotals::decimal(max(0, DocumentTotals::scaled($this->total) - DocumentTotals::scaled($this->creditedAmount())));
    }

    public function customerCredit(): string
    {
        return DocumentTotals::decimal(max(0, DocumentTotals::scaled($this->paidAmount()) + DocumentTotals::scaled($this->creditedAmount()) - DocumentTotals::scaled($this->total) - DocumentTotals::scaled($this->refundedAmount())));
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(InvoiceRefund::class)->orderBy('refunded_on')->orderBy('id');
    }

    public function paymentHistory(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->withTrashed()->orderBy('paid_on')->orderBy('id');
    }

    public function refundedAmount(): string
    {
        $amount = $this->relationLoaded('refunds')
            ? $this->refunds->sum(fn (InvoiceRefund $refund): int => DocumentTotals::scaled($refund->amount))
            : DocumentTotals::scaled($this->refunds()->sum('amount'));

        return DocumentTotals::decimal($amount);
    }

    public function isOverdue(): bool
    {
        return $this->due_date->lt(today('Africa/Johannesburg')) && DocumentTotals::scaled($this->balanceDue()) > 0;
    }

    public function settlementStatus(): string
    {
        if (DocumentTotals::scaled($this->creditedAmount()) >= DocumentTotals::scaled($this->total) && DocumentTotals::scaled($this->total) > 0 && DocumentTotals::scaled($this->balanceDue()) === 0) {
            return 'credited';
        }
        if (DocumentTotals::scaled($this->balanceDue()) === 0) {
            return DocumentTotals::scaled($this->creditedAmount()) > 0 ? 'settled' : 'paid';
        }

        return DocumentTotals::scaled($this->paidAmount()) > 0 ? 'partial' : 'outstanding';
    }
}
