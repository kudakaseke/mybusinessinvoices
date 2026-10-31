<?php

namespace App\Http\Controllers;

use App\DocumentPdf;
use App\DocumentTotals;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $notes = CreditNote::whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request): void {
                $query->where('number', 'like', '%'.$request->string('search').'%')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('customer_name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('invoice_number', 'like', '%'.$request->string('search').'%'));
            }))
            ->with('invoice')->latest('id')->paginate(15)->withQueryString();

        return view('credit-notes.index', compact('notes'));
    }

    public function create(Request $request): View
    {
        $data = $request->validate(['invoice' => ['nullable', 'integer']]);
        $invoices = Invoice::where('user_id', $request->user()->id)->with('creditNotes')->latest('id')->get()
            ->filter(fn (Invoice $invoice): bool => (float) $invoice->creditRemaining() > 0)->values();
        $invoice = empty($data['invoice']) ? null : Invoice::where('user_id', $request->user()->id)->with('creditNotes')->findOrFail($data['invoice']);

        return view('credit-notes.create', compact('invoices', 'invoice'));
    }

    public function store(Request $request, int $record): RedirectResponse
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->findOrFail($record);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'credited_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$invoice->issue_date->toDateString(), 'before_or_equal:'.today('Africa/Johannesburg')->toDateString()],
            'reason' => ['required', 'string', 'max:255'], 'submission_key' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($invoice, $data): void {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->creditNotes()->where('submission_key', $data['submission_key'])->exists()) {
                return;
            }
            if (CreditNote::where('submission_key', $data['submission_key'])->exists()) {
                throw ValidationException::withMessages(['submission_key' => 'Please reload the invoice and try again.']);
            }
            if (DocumentTotals::scaled($data['amount']) > DocumentTotals::scaled($invoice->creditRemaining())) {
                throw ValidationException::withMessages(['amount' => 'The credit cannot exceed the uncredited invoice amount of '.$invoice->currency.' '.$invoice->creditRemaining().'.']);
            }
            if ($invoice->status === 'paid' && ! $invoice->payments()->exists() && DocumentTotals::scaled($invoice->total) > 0) {
                $invoice->payments()->create(['amount' => $invoice->total, 'paid_on' => null, 'reference' => 'Previously marked paid; original payment date unknown']);
            }
            $invoice->creditNotes()->create(Arr::only($data, ['amount', 'credited_on', 'reason', 'submission_key']) + [
                'number' => 'CN-'.$invoice->id.'-'.str_pad((string) ($invoice->creditNotes()->count() + 1), 4, '0', STR_PAD_LEFT),
            ]);
            $invoice->update(['status' => $invoice->settlementStatus()]);
        });

        return redirect()->route('documents.show', ['invoices', $invoice->id])->with('message', 'Credit note issued.');
    }

    public function download(Request $request, int $note, DocumentPdf $pdf): Response
    {
        $creditNote = CreditNote::whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('invoice')->findOrFail($note);

        return response($pdf->renderCreditNote($creditNote), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$creditNote->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
