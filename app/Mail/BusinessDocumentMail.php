<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $documentLabel, public string $number, public string $business, public string $client, public string $currency, public string $amount, public string $pdf, public string $filename, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: preg_replace('/[\\r\\n]+/', ' ', ($this->reminder ? 'Payment reminder: ' : '').$this->documentLabel.' '.$this->number.' - '.$this->business));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.business-document');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
