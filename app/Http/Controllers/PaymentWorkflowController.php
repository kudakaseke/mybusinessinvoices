<?php

namespace App\Http\Controllers;

use App\DocumentPdf;
use App\DocumentTotals;
use App\Mail\BusinessDocumentMail;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PaymentWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $payments = InvoicePayment::query()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('invoice')
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->paginate(20);

        return view('receipts.index', compact('payments'));
    }

    public function show(Request $request, int $record, DocumentPdf $pdf): View
    {
        $payment = $this->ownedReceipt($request, $record);
        $pdfData = base64_encode($pdf->renderReceipt($payment));

        return view('receipts.show', compact('payment', 'pdfData'));
    }

    public function email(Request $request, int $record, DocumentPdf $pdf): RedirectResponse
    {
        $payment = $this->ownedReceipt($request, $record);
        $invoice = $payment->invoice;
        if (! filter_var($invoice->customer_email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'This receipt does not have a valid customer email address.']);
        }

        try {
            Mail::to($invoice->customer_email)->send(new BusinessDocumentMail(
                'Receipt', 'RCT-'.$payment->id, $invoice->business_name, $invoice->customer_name, $invoice->currency,
                (string) $payment->amount, $pdf->renderReceipt($payment), 'Receipt-'.$payment->id.'.pdf',
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['email' => 'The email could not be sent. Please try again later.']);
        }

        return back()->with('message', 'Receipt emailed to '.$invoice->customer_email.' with the PDF attached.');
    }

    private function ownedReceipt(Request $request, int $record): InvoicePayment
    {
        return InvoicePayment::query()->whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('invoice.user')->findOrFail($record);
    }

    public function void(Request $request, int $record, string $payment): RedirectResponse
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->findOrFail($record);
        $invoice->payments()->withTrashed()->findOrFail($payment);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'voided_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$invoice->issue_date->toDateString(), 'before_or_equal:'.today('Africa/Johannesburg')->toDateString()],
        ]);
        DB::transaction(function () use ($invoice, $payment, $data): void {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $entry = $invoice->payments()->withTrashed()->lockForUpdate()->findOrFail($payment);
            if ($entry->trashed()) {
                return;
            }
            if ($entry->paid_on && $data['voided_on'] < $entry->paid_on->toDateString()) {
                throw ValidationException::withMessages(['voided_on' => 'The correction date cannot be before the payment date.']);
            }
            $entry->fill(['void_reason' => $data['reason'], 'voided_on' => $data['voided_on']])->save();
            $entry->delete();
            $invoice->update(['status' => $invoice->settlementStatus()]);
        });

        return back()->with('message', 'Payment reversed. The original remains in the history. Record the correct payment if needed.');
    }

    public function refund(Request $request, int $record): RedirectResponse
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->findOrFail($record);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'refunded_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$invoice->issue_date->toDateString(), 'before_or_equal:'.today('Africa/Johannesburg')->toDateString()],
            'reason' => ['required', 'string', 'max:255'], 'submission_key' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($invoice, $data): void {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->refunds()->where('submission_key', $data['submission_key'])->exists()) {
                return;
            }
            if (InvoiceRefund::where('submission_key', $data['submission_key'])->exists()) {
                throw ValidationException::withMessages(['submission_key' => 'Reload this invoice and try again.']);
            }
            if (DocumentTotals::scaled($data['amount']) > DocumentTotals::scaled($invoice->customerCredit())) {
                throw ValidationException::withMessages(['amount' => 'The refund cannot exceed the available client credit. Issue a credit note for a return first.']);
            }
            $latest = collect([$invoice->payments()->max('paid_on'), $invoice->creditNotes()->max('credited_on')])->filter()->max();
            if ($latest && $data['refunded_on'] < $latest) {
                throw ValidationException::withMessages(['refunded_on' => 'The refund date cannot be before the payments and credit notes supporting it.']);
            }
            $invoice->refunds()->create($data);
            $invoice->update(['status' => $invoice->settlementStatus()]);
        });

        return back()->with('message', 'Cash refund recorded.');
    }

    public function receipt(Request $request, int $record, string $payment, DocumentPdf $pdf): Response
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->findOrFail($record);
        $entry = $invoice->payments()->findOrFail($payment);

        return response($pdf->renderReceipt($entry), 200, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="Receipt-'.$entry->id.'.pdf"',
        ]);
    }
}
