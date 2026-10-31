<?php

namespace App\Http\Controllers;

use App\DocumentTotals;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceController extends Controller
{
    public function calendar(Request $request): View
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = empty($data['month'])
            ? CarbonImmutable::today('Africa/Johannesburg')->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $data['month'], 'Africa/Johannesburg');
        $start = $month->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $userId = $request->user()->id;
        $events = collect();
        foreach (['invoices' => Invoice::class, 'quotations' => Quotation::class] as $kind => $model) {
            $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
            $documents = $model::where('user_id', $userId)->where('issue_date', '>=', $start->toDateString())
                ->where('issue_date', '<', $end->addDay()->toDateString())->orderBy('id')->get();
            foreach ($documents as $document) {
                $events->push([
                    'date' => $document->issue_date->toDateString(), 'type' => $kind,
                    'label' => $kind === 'invoices' ? 'Invoice' : 'Quotation', 'number' => $document->$number,
                    'name' => $document->customer_name, 'amount' => $document->total, 'currency' => $document->currency,
                    'url' => route('documents.show', [$kind, $document->id]),
                ]);
            }
        }
        $payments = InvoicePayment::withTrashed()->whereHas('invoice', fn ($query) => $query->where('user_id', $userId))
            ->where(fn ($query) => $query->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])->orWhereBetween('voided_on', [$start->toDateString(), $end->toDateString()]))
            ->with('invoice')->orderBy('id')->get();
        foreach ($payments as $payment) {
            if ($payment->paid_on && $payment->paid_on->toDateString() >= $start->toDateString() && $payment->paid_on->toDateString() <= $end->toDateString()) {
                $events->push([
                    'date' => $payment->paid_on->toDateString(), 'type' => 'payments', 'label' => 'Received payment',
                    'number' => $payment->invoice->invoice_number, 'name' => $payment->invoice->customer_name,
                    'amount' => $payment->amount, 'currency' => $payment->invoice->currency,
                    'url' => route('documents.show', ['invoices', $payment->invoice_id]),
                ]);
            }
            if ($payment->trashed() && $payment->voided_on && $payment->voided_on->toDateString() >= $start->toDateString() && $payment->voided_on->toDateString() <= $end->toDateString()) {
                $events->push([
                    'date' => $payment->voided_on->toDateString(), 'type' => 'payments', 'label' => 'Payment reversal',
                    'number' => $payment->invoice->invoice_number, 'name' => $payment->invoice->customer_name,
                    'amount' => '-'.$payment->amount, 'currency' => $payment->invoice->currency,
                    'url' => route('documents.show', ['invoices', $payment->invoice_id]),
                ]);
            }
        }
        foreach (InvoiceRefund::whereHas('invoice', fn ($query) => $query->where('user_id', $userId))->whereBetween('refunded_on', [$start->toDateString(), $end->toDateString()])->with('invoice')->get() as $refund) {
            $events->push([
                'date' => $refund->refunded_on->toDateString(), 'type' => 'payments', 'label' => 'Cash refund',
                'number' => $refund->invoice->invoice_number, 'name' => $refund->invoice->customer_name,
                'amount' => '-'.$refund->amount, 'currency' => $refund->invoice->currency,
                'url' => route('documents.show', ['invoices', $refund->invoice_id]),
            ]);
        }
        $expenses = Expense::where('user_id', $userId)->where('expense_date', '>=', $start->toDateString())
            ->where('expense_date', '<', $end->addDay()->toDateString())->orderBy('id')->get();
        foreach ($expenses as $expense) {
            $events->push([
                'date' => $expense->expense_date->toDateString(), 'type' => 'expenses', 'label' => 'Expense',
                'number' => $expense->description, 'name' => $expense->merchant,
                'amount' => $expense->amount, 'currency' => $expense->currency,
                'url' => route('records.edit', ['expenses', $expense->id]),
            ]);
        }
        $creditNotes = CreditNote::whereHas('invoice', fn ($query) => $query->where('user_id', $userId))
            ->where('credited_on', '>=', $start->toDateString())->where('credited_on', '<', $end->addDay()->toDateString())
            ->with('invoice')->orderBy('id')->get();
        foreach ($creditNotes as $note) {
            $events->push([
                'date' => $note->credited_on->toDateString(), 'type' => 'credit-notes', 'label' => 'Credit note',
                'number' => $note->number, 'name' => $note->invoice->customer_name,
                'amount' => $note->amount, 'currency' => $note->invoice->currency,
                'url' => route('documents.show', ['invoices', $note->invoice_id]),
            ]);
        }
        $events = $events->groupBy('date');
        $dailyTotals = $events->map(fn ($dayEvents) => $dayEvents->groupBy('currency')->map(fn ($records, string $currency): array => [
            'currency' => $currency,
            'invoiced' => $records->where('type', 'invoices')->sum(fn (array $event): int => DocumentTotals::scaled($event['amount'])),
            'received' => $records->where('type', 'payments')->sum(fn (array $event): int => DocumentTotals::scaled($event['amount'])),
            'expenses' => $records->where('type', 'expenses')->sum(fn (array $event): int => DocumentTotals::scaled($event['amount'])),
            'credited' => $records->where('type', 'credit-notes')->sum(fn (array $event): int => DocumentTotals::scaled($event['amount'])),
            'quoted' => $records->where('type', 'quotations')->sum(fn (array $event): int => DocumentTotals::scaled($event['amount'])),
        ])->values());

        return view('calendar', compact('month', 'start', 'end', 'events', 'dailyTotals'));
    }

    public function overview(Request $request): View
    {
        $userId = $request->user()->id;
        $title = ucfirst($request->route()->getName());
        $invoices = Invoice::where('user_id', $userId);
        $received = "CASE WHEN status = 'paid' AND NOT EXISTS (SELECT 1 FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id) THEN total ELSE COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id AND invoice_payments.deleted_at IS NULL), 0) END - COALESCE((SELECT SUM(amount) FROM invoice_refunds WHERE invoice_refunds.invoice_id = invoices.id), 0)";
        $credits = 'COALESCE((SELECT SUM(amount) FROM credit_notes WHERE credit_notes.invoice_id = invoices.id), 0)';
        $balance = "total - ($received) - ($credits)";
        $totals = (clone $invoices)->selectRaw("currency, SUM(total) AS billed, SUM($received) AS paid, SUM($credits) AS credited, SUM(CASE WHEN ($balance) > 0 THEN ($balance) ELSE 0 END) AS outstanding, SUM(CASE WHEN ($balance) < 0 THEN -($balance) ELSE 0 END) AS client_credit")->groupBy('currency')->get();
        $expenses = Expense::where('user_id', $userId)->selectRaw('currency, SUM(amount) AS amount')->groupBy('currency')->pluck('amount', 'currency');
        $monthly = (clone $invoices)->selectRaw('SUBSTR(issue_date, 1, 7) AS month, currency, SUM(total) AS amount')->groupBy('month', 'currency')->orderByDesc('month')->limit(24)->get();
        $categories = Expense::where('user_id', $userId)->selectRaw('category, currency, SUM(amount) AS amount')->groupBy('category', 'currency')->orderBy('category')->get();
        $recent = (clone $invoices)->latest('id')->limit(8)->get();
        $counts = [
            'Invoices' => (clone $invoices)->count(),
            'Quotations' => Quotation::where('user_id', $userId)->count(),
            'Clients' => Client::where('user_id', $userId)->count(),
            'Products' => Product::where('user_id', $userId)->count(),
        ];

        $activity = $title === 'Analytics' ? $this->analyticsActivity($request) : null;

        return view('dashboard', compact('title', 'totals', 'expenses', 'monthly', 'categories', 'recent', 'counts', 'activity'));
    }

    /** @return array{currency: string, weeks: array, invoiced: int, received: int, expenses: int, undatedPayments: bool} */
    private function analyticsActivity(Request $request): array
    {
        $data = $request->validate(['currency' => ['nullable', 'in:USD,ZWG']]);
        $currency = $data['currency'] ?? 'USD';
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $start = $today->startOfWeek()->subWeeks(11);
        $weeks = [];
        for ($index = 0; $index < 12; $index++) {
            $week = $start->addWeeks($index);
            $weeks[$week->toDateString()] = ['date' => $week->toDateString(), 'label' => $week->format('d M'), 'invoiced' => 0, 'received' => 0, 'expenses' => 0];
        }
        $invoices = Invoice::where('user_id', $request->user()->id)->where('currency', $currency)
            ->where('issue_date', '>=', $start->toDateString())->where('issue_date', '<', $today->addDay()->toDateString())
            ->selectRaw('SUBSTR(issue_date, 1, 10) AS day, SUM(total) AS amount')->groupBy('day')->get();
        $payments = InvoicePayment::withTrashed()->whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id)->where('currency', $currency))
            ->where('paid_on', '>=', $start->toDateString())->where('paid_on', '<', $today->addDay()->toDateString())
            ->selectRaw('SUBSTR(paid_on, 1, 10) AS day, SUM(amount) AS amount')->groupBy('day')->get();
        $reversals = InvoicePayment::withTrashed()->whereNotNull('deleted_at')->whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id)->where('currency', $currency))
            ->whereBetween('voided_on', [$start->toDateString(), $today->toDateString()])
            ->selectRaw('SUBSTR(voided_on, 1, 10) AS day, SUM(amount) AS amount')->groupBy('day')->get();
        $refunds = InvoiceRefund::whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id)->where('currency', $currency))
            ->whereBetween('refunded_on', [$start->toDateString(), $today->toDateString()])
            ->selectRaw('SUBSTR(refunded_on, 1, 10) AS day, SUM(amount) AS amount')->groupBy('day')->get();
        foreach ($reversals->concat($refunds) as $entry) {
            $entry->amount = '-'.$entry->amount;
            $payments->push($entry);
        }
        $expenses = Expense::where('user_id', $request->user()->id)->where('currency', $currency)
            ->where('expense_date', '>=', $start->toDateString())->where('expense_date', '<', $today->addDay()->toDateString())
            ->selectRaw('SUBSTR(expense_date, 1, 10) AS day, SUM(amount) AS amount')->groupBy('day')->get();
        foreach (['invoiced' => $invoices, 'received' => $payments, 'expenses' => $expenses] as $field => $records) {
            foreach ($records as $record) {
                $week = CarbonImmutable::parse($record->day, 'Africa/Johannesburg')->startOfWeek()->toDateString();
                $weeks[$week][$field] += DocumentTotals::scaled($record->amount);
            }
        }
        $undatedPayments = InvoicePayment::whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id)->where('currency', $currency))
            ->whereNull('paid_on')->exists();

        return ['currency' => $currency, 'weeks' => array_values($weeks), 'invoiced' => array_sum(array_column($weeks, 'invoiced')), 'received' => array_sum(array_column($weeks, 'received')), 'expenses' => array_sum(array_column($weeks, 'expenses')), 'undatedPayments' => $undatedPayments];
    }

    private function model(string $kind): string
    {
        return match ($kind) {
            'clients' => Client::class,
            'products' => Product::class,
            'expenses' => Expense::class,
            default => abort(404),
        };
    }

    public function index(Request $request, string $kind): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $model = $this->model($kind);
        $field = $kind === 'expenses' ? 'merchant' : 'name';
        $records = $model::where('user_id', $request->user()->id)
            ->when($request->filled('search'), fn ($query) => $query->where($field, 'like', '%'.$request->string('search').'%'))
            ->latest('id')->paginate(15)->withQueryString();

        return view('records.index', compact('records', 'kind'));
    }

    public function form(Request $request, string $kind, ?int $record = null): View
    {
        $model = $this->model($kind);
        $entry = $record === null ? new $model(['currency' => $request->user()->base_currency ?? 'USD', 'expense_date' => today()]) : $model::where('user_id', $request->user()->id)->findOrFail($record);

        return view('records.form', compact('entry', 'kind'));
    }

    public function save(Request $request, string $kind, ?int $record = null): RedirectResponse
    {
        $model = $this->model($kind);
        $entry = $record === null ? new $model : $model::where('user_id', $request->user()->id)->findOrFail($record);
        $rules = match ($kind) {
            'clients' => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('clients')->where('user_id', $request->user()->id)->ignore($entry->id)],
                'phone' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:10000'],
            ],
            'products' => [
                'name' => ['required', 'string', 'max:255', Rule::unique('products')->where('user_id', $request->user()->id)->ignore($entry->id)],
                'description' => ['nullable', 'string', 'max:10000'],
                'currency' => ['required', Rule::in(['USD', 'ZWG'])], 'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            ],
            'expenses' => [
                'merchant' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', 'max:255'], 'expense_date' => ['required', 'date'],
                'currency' => ['required', Rule::in(['USD', 'ZWG'])], 'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
                'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ],
        };
        $data = $request->validate($rules);
        unset($data['receipt']);
        DB::transaction(function () use ($request, $kind, $entry, $data): void {
            if ($kind === 'expenses') {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $date = CarbonImmutable::parse($data['expense_date'])->toDateString();
                $duplicate = Expense::where('user_id', $request->user()->id)
                    ->when($entry->exists, fn ($query) => $query->where('id', '!=', $entry->id))
                    ->whereDate('expense_date', $date)->where('currency', $data['currency'])->where('amount', $data['amount']);
                foreach (['merchant', 'description', 'category'] as $field) {
                    $duplicate->whereRaw('LOWER('.$field.') = ?', [mb_strtolower($data[$field])]);
                }
                if ($duplicate->exists()) {
                    throw ValidationException::withMessages(['description' => 'This expense is already recorded with the same date, merchant, description, category, amount and currency. Check your expenses before saving it again.']);
                }
            }
            if ($request->hasFile('receipt')) {
                $data['receipt_path'] = $request->file('receipt')->store('receipts/'.$request->user()->id, 'local');
                $data['receipt_original_name'] = $request->file('receipt')->getClientOriginalName();
            }
            $entry->fill($data);
            $entry->user_id = $request->user()->id;
            $entry->save();
        });

        return redirect()->route('records.index', $kind)->with('message', 'Record saved.');
    }

    public function receipt(Request $request, int $record): StreamedResponse
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($record);
        abort_unless($expense->receipt_path && ! str_contains($expense->receipt_path, '..') && str_starts_with($expense->receipt_path, 'receipts/') && Storage::disk('local')->exists($expense->receipt_path), 404, 'The receipt file was not recovered.');

        return Storage::disk('local')->download($expense->receipt_path, $expense->receipt_original_name);
    }

    public function settings(Request $request): View
    {
        return view('settings.business', ['user' => $request->user()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (['business_name', 'business_phone', 'whatsapp_number'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        $rules += [
            'business_address' => ['nullable', 'string', 'max:10000'],
            'business_email' => ['sometimes', 'required', 'email', 'max:255', Rule::in([$request->user()->business_email ?? $request->user()->email])],
            'website' => ['nullable', 'url', 'max:255'], 'base_currency' => ['required', Rule::in(['USD', 'ZWG'])],
            'target_currency' => ['required', Rule::in(['USD', 'ZWG']), 'different:base_currency'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001', 'max:99999999.999999', 'decimal:0,6'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'default_currencies' => ['required', 'array', 'min:1'],
            'payment_reminders_enabled' => ['sometimes', 'boolean'],
            'default_currencies.*' => ['required', Rule::in(['USD', 'ZWG']), 'distinct'],
        ];
        $data = $request->validate($rules);
        $data['business_email'] = $request->user()->business_email ?? $request->user()->email;
        $profile = array_replace($request->user()->only('business_name', 'business_email', 'business_phone', 'business_address'), $data);
        $complete = collect(['business_name', 'business_email', 'business_phone', 'business_address'])
            ->every(fn (string $field): bool => filled($profile[$field] ?? null));
        $data['business_profile_completed_at'] = $complete ? ($request->user()->business_profile_completed_at ?? now()) : null;
        if ($data['base_currency'] === 'USD' && $data['target_currency'] === 'ZWG') {
            $data['usd_to_zwg_rate'] = round((float) $data['exchange_rate'], 4);
        } else {
            $data['usd_to_zwg_rate'] = round(1 / (float) $data['exchange_rate'], 4);
        }
        $request->user()->fill($data)->save();

        return back()->with('message', 'Business settings saved.');
    }
}
