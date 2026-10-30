<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('documents.create', ['kind' => $this->route('kind')]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $table = $this->route('kind');
        $number = $table === 'invoices' ? 'invoice_number' : 'quotation_number';
        $rules = [
            $number => ['required', 'string', 'max:255', Rule::unique($table, $number)->where('user_id', $this->user()->id)->ignore($this->route('record'))],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(['USD', 'ZWG'])],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:product_id,description,quantity,unit_price'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('user_id', $this->user()->id)->where('currency', $this->input('currency'))],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,2'],
            'payment_account_currency' => ['nullable', Rule::in(['USD', 'ZWG'])],
            'prepared_by' => ['required', 'string', 'max:255'],
        ];
        foreach (['business_name', 'business_email', 'customer_name', 'customer_email'] as $field) {
            $rules[$field] = ['required', str_ends_with($field, 'email') ? 'email' : 'string', 'max:255'];
        }
        foreach (['business_phone', 'customer_phone', 'payment_account_name', 'payment_account_number', 'payment_bank_name'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        foreach (['business_address', 'customer_address'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:10000'];
        }
        if ($table === 'invoices') {
            $rules['status'] = ['required', Rule::in(['outstanding', 'paid'])];
        } else {
            $rules['notes'] = ['nullable', 'string', 'max:10000'];
        }

        return $rules;
    }
}
