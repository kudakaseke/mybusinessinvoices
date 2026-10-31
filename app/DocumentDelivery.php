<?php

namespace App;

use App\Mail\BusinessDocumentMail;
use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DocumentDelivery
{
    public function send(Model $document, string $kind, bool $reminder = false): void
    {
        Cache::lock('document-delivery:'.$kind.':'.$document->id, 120)->block(5, function () use ($document, $kind, $reminder): void {
            $document->refresh()->load($kind === 'invoices' ? ['items', 'payments', 'creditNotes', 'refunds'] : ['items']);
            if ($reminder && (! $document instanceof Invoice || ! $document->isOverdue())) {
                throw ValidationException::withMessages(['email' => 'This invoice does not have an overdue balance.']);
            }
            if ($reminder && $document->last_reminded_at?->gt(now()->subDay())) {
                throw ValidationException::withMessages(['email' => 'A reminder was already sent within the last 24 hours.']);
            }
            $label = $kind === 'invoices' ? 'Invoice' : 'Quotation';
            $number = $kind === 'invoices' ? $document->invoice_number : $document->quotation_number;
            $filename = $label.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $number).'.pdf';
            try {
                Mail::to($document->customer_email)->send(new BusinessDocumentMail(
                    $label, $number, $document->business_name, $document->customer_name, $document->currency,
                    $document instanceof Invoice ? $document->balanceDue() : (string) $document->total,
                    app(DocumentPdf::class)->render($document, $kind), $filename, $reminder,
                ));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
                throw ValidationException::withMessages(['email' => 'The email could not be sent. Please try again later.']);
            }
            if ($reminder) {
                $document->forceFill(['last_reminded_at' => now()])->save();
            }
            ActivityLog::create([
                'user_id' => $document->user_id, 'action' => $reminder ? 'reminder_sent' : 'emailed',
                'record_type' => class_basename($document), 'record_id' => $document->id,
                'description' => $label.' '.$number.($reminder ? ' payment reminder sent' : ' emailed with PDF'),
                'changes' => ['recipient' => $document->customer_email],
            ]);
        });
    }
}
