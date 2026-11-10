<?php

namespace App\Http\Controllers;

use App\DocumentPdf;
use App\DocumentTotals;
use App\Models\Expense;
use App\Models\Invoice;
use App\StatementExcel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StatementController extends Controller
{
    public function index(Request $request): View
    {
        return view('statements.index', $this->statementData($request));
    }

    public function excel(Request $request, StatementExcel $excel): Response
    {
        $data = $this->statementData($request);

        return response($excel->render($data, $request->user()->business_name ?? $request->user()->name), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Client-statement-'.$data['currency'].'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, DocumentPdf $pdf): Response
    {
        $data = $this->statementData($request);
        $business = $request->user()->business_name ?? $request->user()->name;

        return response($pdf->renderStatement($data, $business), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Client-statement-'.$data['currency'].'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{clients: \Illuminate\Database\Eloquent\Collection, client: ?string, currency: string, entries: Collection, balance: int, charged: int, received: int, credited: int, daily: Collection, openingBalance: int, from: ?string, to: ?string, paymentInvoices: \Illuminate\Database\Eloquent\Collection, expenseEntries: Collection, expenseTotal: int} */
    private function statementData(Request $request): array
    {
        $data = $request->validate([
            'client' => ['nullable', 'string', 'max:255'], 'currency' => ['nullable', 'in:USD,ZWG'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $clients = Invoice::where('user_id', $request->user()->id)->selectRaw('LOWER(customer_email) AS customer_email')
            ->selectRaw('MAX(customer_name) AS customer_name')->groupByRaw('LOWER(customer_email)')->orderBy('customer_name')->get();
        $client = isset($data['client']) ? strtolower($data['client']) : null;
        $currency = $data['currency'] ?? 'USD';
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->when($client, fn ($query) => $query->whereRaw('LOWER(customer_email) = ?', [$client]))
            ->where('currency', $currency)->with(['payments', 'paymentHistory', 'creditNotes', 'refunds'])->orderBy('issue_date')->orderBy('id')->get();
        $entries = collect();
        foreach ($invoices as $invoice) {
            $entries->push([
                'date' => $invoice->issue_date->toDateString(), 'date_label' => $invoice->issue_date->format('d M Y'),
                'client' => $invoice->customer_name, 'invoice' => $invoice, 'description' => 'Invoice '.$invoice->invoice_number,
                'charged' => DocumentTotals::scaled($invoice->total), 'received' => 0, 'credited' => 0, 'order' => 0,
            ]);
            foreach ($invoice->paymentHistory as $payment) {
                $entries->push([
                    'date' => $payment->paid_on?->toDateString() ?? $invoice->issue_date->toDateString(),
                    'date_label' => $payment->paid_on?->format('d M Y') ?? 'Original date unknown',
                    'client' => $invoice->customer_name, 'invoice' => $invoice,
                    'description' => 'Payment for '.$invoice->invoice_number.($payment->reference ? ' — '.$payment->reference : ''),
                    'charged' => 0, 'received' => DocumentTotals::scaled($payment->amount), 'credited' => 0, 'order' => 1,
                ]);
                if ($payment->trashed()) {
                    $entries->push([
                        'date' => $payment->voided_on->toDateString(), 'date_label' => $payment->voided_on->format('d M Y'),
                        'client' => $invoice->customer_name, 'invoice' => $invoice,
                        'description' => 'Payment reversal for '.$invoice->invoice_number.': '.$payment->void_reason,
                        'charged' => 0, 'received' => -DocumentTotals::scaled($payment->amount), 'credited' => 0, 'order' => 3,
                    ]);
                }
            }
            foreach ($invoice->refunds as $refund) {
                $entries->push([
                    'date' => $refund->refunded_on->toDateString(), 'date_label' => $refund->refunded_on->format('d M Y'),
                    'client' => $invoice->customer_name, 'invoice' => $invoice,
                    'description' => 'Cash refund for '.$invoice->invoice_number.': '.$refund->reason,
                    'charged' => 0, 'received' => -DocumentTotals::scaled($refund->amount), 'credited' => 0, 'order' => 4,
                ]);
            }
            foreach ($invoice->creditNotes as $note) {
                $entries->push([
                    'date' => $note->credited_on->toDateString(), 'date_label' => $note->credited_on->format('d M Y'),
                    'client' => $invoice->customer_name, 'invoice' => $invoice,
                    'description' => 'Credit note '.$note->number.' for '.$invoice->invoice_number.' — '.$note->reason,
                    'charged' => 0, 'received' => 0, 'credited' => DocumentTotals::scaled($note->amount), 'order' => 2,
                ]);
            }
        }
        $paymentInvoices = $invoices->filter(fn (Invoice $invoice): bool => (float) $invoice->balanceDue() > 0)->values();
        $openingBalance = $from ? $entries->filter(fn (array $entry): bool => $entry['date_label'] !== 'Original date unknown' && $entry['date'] < $from)
            ->sum(fn (array $entry): int => $entry['charged'] - $entry['received'] - $entry['credited']) : 0;
        $entries = $entries->filter(fn (array $entry): bool => (! $to || $entry['date'] <= $to)
            && ($entry['date_label'] === 'Original date unknown' || ! $from || $entry['date'] >= $from));
        $balance = $openingBalance;
        $entries = $entries->sortBy([['date', 'asc'], ['order', 'asc']])->values()->map(function (array $entry) use (&$balance): array {
            $balance += $entry['charged'] - $entry['received'] - $entry['credited'];

            return $entry + ['balance' => $balance];
        });
        $charged = $entries->sum('charged');
        $received = $entries->sum('received');
        $credited = $entries->sum('credited');
        $expenseEntries = $client ? collect() : Expense::where('user_id', $request->user()->id)->where('currency', $currency)
            ->when($from, fn ($query) => $query->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date')->orderBy('id')->get();
        $expenseTotal = $expenseEntries->sum(fn (Expense $expense): int => DocumentTotals::scaled($expense->amount));
        $daily = $entries->groupBy(fn (array $entry): string => $entry['date_label'] === 'Original date unknown' ? 'unknown' : $entry['date'])
            ->map(fn (Collection $rows): array => [
                'date_label' => $rows->first()['date_label'],
                'invoice_count' => $rows->where('order', 0)->count(),
                'charged' => $rows->sum('charged'), 'received' => $rows->sum('received'), 'credited' => $rows->sum('credited'),
                'balance' => $rows->last()['balance'],
            ])->values();

        return compact('clients', 'client', 'currency', 'entries', 'balance', 'charged', 'received', 'credited', 'daily', 'openingBalance', 'from', 'to', 'paymentInvoices', 'expenseEntries', 'expenseTotal');
    }
}
