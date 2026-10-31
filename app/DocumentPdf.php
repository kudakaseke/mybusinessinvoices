<?php

namespace App;

use App\Models\CreditNote;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DocumentPdf
{
    private array $pages = [];

    private string $content = '';

    private int $y = 40;

    private function text(string $text, int $x, int $y, int $size = 10, bool $bold = false, bool $uppercase = true): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $uppercase ? mb_strtoupper($text) : $text) ?: '';
        $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
        $font = $bold ? 'F2' : 'F1';
        $this->content .= "BT /$font $size Tf 1 0 0 1 $x ".(842 - $y)." Tm ($encoded) Tj ET\n";
    }

    private function line(int $y, int $start = 30, int $end = 565): void
    {
        $this->content .= "0.7 G $start ".(842 - $y)." m $end ".(842 - $y)." l S 0 G\n";
    }

    private function space(int $height): void
    {
        if ($this->y + $height > 770) {
            $this->pages[] = $this->content;
            $this->content = '';
            $this->y = 45;
        }
    }

    private function block(string $text, int $x, int $y, int $width = 42, bool $uppercase = true): int
    {
        $lines = explode("\n", wordwrap(str_replace("\r", '', $text), $width, "\n", true));
        foreach ($lines as $line) {
            $this->text($line, $x, $y, 10, false, $uppercase);
            $y += 14;
        }

        return $y;
    }

    public function render(Model $document, string $kind): string
    {
        $this->pages = [];
        $this->content = '';
        $this->y = 40;
        $invoice = $kind === 'invoices';
        $title = $invoice ? 'Invoice' : 'Quotation';
        $number = $invoice ? $document->invoice_number : $document->quotation_number;
        $money = fn (mixed $amount): string => ($document->currency === 'USD' ? '$' : '').number_format((float) $amount, 2);
        $this->text($title, 30, 45, 20, true);
        $this->block((string) $document->business_name, 420, 43, 22);
        foreach ([[$title.' number', $number], ['Date of issue', $document->issue_date->format('F j, Y')], [$invoice ? 'Date due' : 'Valid until', $document->due_date->format('F j, Y')]] as $index => [$label, $value]) {
            $this->text($label, 30, 76 + $index * 14, 10, $index === 0);
            $this->text($value, 125, 76 + $index * 14);
        }
        $this->text((string) $document->business_name, 30, 139, 10, true);
        $businessEnd = $this->block(implode("\n", array_filter([$document->business_address, $document->business_email, $document->business_phone])), 30, 155, 36);
        $this->text('Bill to', 245, 139, 10, true);
        $customerEnd = 155;
        foreach ([
            'customer_name' => 'Customer Name:',
            'customer_address' => 'Customer Physical Address:',
            'customer_phone' => 'Phone number:',
            'customer_email' => 'Customer Email Address:',
        ] as $field => $label) {
            $this->text($label, 245, $customerEnd, 10, true);
            $customerEnd = $this->block((string) ($document->$field ?? ''), 245, $customerEnd + 14, 50, $field !== 'customer_email');
        }
        $this->y = max($businessEnd, $customerEnd) + 24;
        $this->text($money($invoice && $document->exists && $document->status !== 'paid' ? $document->balanceDue() : $document->total).' '.$document->currency.($invoice ? ($document->status === 'paid' ? ' paid' : ' due '.$document->due_date->format('F j, Y')) : ' quoted'), 30, $this->y, 14, true);
        $this->y += 65;
        foreach ([['Description', 30], ['Qty', 420], ['Unit price', 455], ['Amount', 520]] as [$label, $x]) {
            $this->text($label, $x, $this->y, 8);
        }
        $this->line($this->y + 7);
        $this->y += 23;
        foreach ($document->items as $item) {
            $height = count(explode("\n", wordwrap($item->description, 65, "\n", true))) * 14 + 8;
            $this->space($height);
            $end = $this->block($item->description, 30, $this->y, 65);
            $this->text((string) (float) $item->quantity, 420, $this->y);
            $this->text($money($item->unit_price), 455, $this->y);
            $this->text($money($item->amount), 520, $this->y);
            $this->y = $end + 8;
        }
        $this->y += 14;
        $this->space(150);
        $rows = [['Subtotal', $money($document->subtotal)]];
        if ((float) $document->discount_rate > 0) {
            $rows[] = ['Discount ('.(float) $document->discount_rate.'% off)', '-'.$money($document->discount_amount)];
        }
        if ((float) $document->vat_rate > 0) {
            $rows[] = ['VAT ('.(float) $document->vat_rate.'%)', $money($document->vat_amount)];
        }
        $rows[] = ['Total', $money($document->total)];
        if ($invoice && $document->exists) {
            $rows[] = ['Credit notes', $money($document->creditedAmount()).' '.$document->currency];
        }
        if ($invoice) {
            $rows[] = ['Payments received', $money($document->exists ? $document->paidAmount() : 0).' '.$document->currency];
            if ($document->exists && (float) $document->refundedAmount() > 0) {
                $rows[] = ['Cash refunded', $money($document->refundedAmount()).' '.$document->currency];
            }
            if ($document->exists && (float) $document->customerCredit() > 0) {
                $rows[] = ['Client credit (not refunded)', $money($document->customerCredit()).' '.$document->currency];
            }
        }
        $rows[] = [$invoice ? 'Amount due' : 'Quotation total', $money($invoice && $document->exists ? $document->balanceDue() : $document->total).' '.$document->currency];
        foreach ($rows as $index => [$label, $value]) {
            $this->line($this->y - 10, 300);
            $bold = $index === count($rows) - 1;
            $this->text($label, 300, $this->y, 10, $bold);
            $this->text($value, 505 - max(0, strlen($value) - 10) * 5, $this->y, 10, $bold);
            $this->y += 15;
        }
        $this->y += 25;
        foreach (['payment_bank_name' => 'Bank', 'payment_account_name' => 'Account name', 'payment_account_number' => 'Account number', 'payment_account_currency' => 'Account currency', 'notes' => 'Notes', 'prepared_by' => 'Prepared by'] as $field => $label) {
            if ($document->$field) {
                foreach (explode("\n", wordwrap($label.': '.$document->$field, 95, "\n", true)) as $line) {
                    $this->space(16);
                    $this->text($line, 30, $this->y, 9);
                    $this->y += 16;
                }
            }
        }
        $this->pages[] = $this->content;

        return $this->encode();
    }

    /** @param array{clients: Collection, client: ?string, currency: string, entries: Collection, balance: int, charged: int, received: int, credited: int, daily: Collection, openingBalance: int, from: ?string, to: ?string, paymentInvoices: Collection, expenseEntries: Collection, expenseTotal: int} $data */
    public function renderStatement(array $data, string $business): string
    {
        $this->pages = [];
        $this->content = '';
        $this->y = 45;
        $this->text('Client statement', 30, $this->y, 20, true);
        $this->y = $this->block($business, 30, $this->y + 26, 80);
        $name = $data['client'] ? ($data['clients']->firstWhere('customer_email', $data['client'])?->customer_name ?? 'Client statement') : 'All clients';
        $this->y = $this->block($name, 30, $this->y + 10, 80);
        $this->y = $this->block($data['client'] ?? '', 30, $this->y, 80, false);
        $this->text('Currency: '.$data['currency'].' | Generated: '.now('Africa/Johannesburg')->format('d M Y'), 30, $this->y + 10);
        $this->text('Invoice charges increase the balance; payments received reduce it.', 30, $this->y + 28, 9);
        $this->y += 54;
        if ($data['from'] || $data['to']) {
            $this->text('Period: '.($data['from'] ?? 'Beginning').' to '.($data['to'] ?? 'Latest'), 30, $this->y, 9);
            $this->y += 18;
            $this->text('Opening balance: '.$data['currency'].' '.number_format($data['openingBalance'] / 100, 2), 30, $this->y, 9);
            $this->y += 24;
        }
        $this->text('Daily summary', 30, $this->y, 12, true);
        $this->y += 22;
        foreach ($data['daily'] as $day) {
            $this->space(75);
            $this->text($day['date_label'].' | Invoices: '.$day['invoice_count'], 30, $this->y, 9, true);
            $this->y += 16;
            $this->text('Charges '.number_format($day['charged'] / 100, 2).' | Payments '.number_format($day['received'] / 100, 2).' | Credits '.number_format($day['credited'] / 100, 2).' | Balance '.number_format($day['balance'] / 100, 2), 30, $this->y, 9);
            $this->y += 25;
        }
        $this->space(100);
        $this->text('Transaction details', 30, $this->y, 12, true);
        $this->y += 24;
        $heading = function (): void {
            foreach ([['Date', 30], ['Details', 130], ['Charge', 330], ['Payment', 395], ['Credit note', 455], ['Balance', 520]] as [$label, $position]) {
                $this->text($label, $position, $this->y, 8, true);
            }
            $this->line($this->y + 7);
            $this->y += 24;
        };
        $heading();
        foreach ($data['entries'] as $entry) {
            if (! $data['client']) {
                $entry['description'] = $entry['client'].' — '.$entry['description'];
            }
            $height = max(count(explode("\n", wordwrap($entry['description'], 29, "\n", true))), count(explode("\n", wordwrap($entry['date_label'], 15, "\n", true)))) * 14 + 10;
            $pageCount = count($this->pages);
            $this->space($height + 24);
            if (count($this->pages) !== $pageCount) {
                $heading();
            }
            $this->block($entry['date_label'], 30, $this->y, 15);
            $this->block($entry['description'], 130, $this->y, 29);
            foreach (['charged' => 330, 'received' => 395, 'credited' => 455, 'balance' => 520] as $field => $position) {
                $this->text(number_format($entry[$field] / 100, 2), $position, $this->y, 7);
            }
            $this->y += $height;
        }
        if ($data['entries']->isEmpty()) {
            $this->text('No invoice activity for this client and currency.', 30, $this->y, 10);
            $this->y += 24;
        }
        $this->space(115);
        $this->y += 15;
        foreach (['charged' => 'Invoice charges', 'received' => 'Payments received', 'credited' => 'Credit notes', 'balance' => ($data['balance'] < 0 ? 'Client credit' : 'Still to pay')] as $field => $label) {
            $this->text($label.': '.$data['currency'].' '.number_format(abs($data[$field]) / 100, 2), 30, $this->y, 11, true);
            $this->y += 20;
        }
        if (! $data['client']) {
            $this->space(85);
            $this->y += 15;
            $this->text('Business expenses', 30, $this->y, 12, true);
            $this->y += 22;
            foreach ($data['expenseEntries'] as $expense) {
                $details = $expense->expense_date->format('d M Y').' | '.$expense->merchant.' | '.$expense->category.' | '.$expense->description;
                $height = count(explode("\n", wordwrap($details, 76, "\n", true))) * 14 + 12;
                $this->space($height);
                $this->block($details, 30, $this->y, 76);
                $this->text(number_format($expense->amount, 2), 505, $this->y, 9);
                $this->y += $height;
            }
            $this->space(30);
            $this->text('Total expenses: '.$data['currency'].' '.number_format($data['expenseTotal'] / 100, 2), 30, $this->y, 11, true);
        }
        $this->pages[] = $this->content;

        return $this->encode();
    }

    public function renderCreditNote(CreditNote $note): string
    {
        $this->pages = [];
        $this->content = '';
        $invoice = $note->invoice;
        $this->text('Credit note', 30, 45, 20, true);
        $this->y = $this->block($invoice->business_name, 30, 75, 80);
        $this->y = $this->block('Credit note: '.$note->number, 30, $this->y + 15, 80);
        $this->y = $this->block('Original invoice: '.$invoice->invoice_number, 30, $this->y, 80);
        $this->text('Date: '.$note->credited_on->format('d M Y'), 30, $this->y);
        $this->y += 30;
        $this->y = $this->block('Client: '.$invoice->customer_name, 30, $this->y, 80);
        $this->y = $this->block($invoice->customer_email, 30, $this->y, 80, false);
        $this->y = $this->block($invoice->customer_address ?? '', 30, $this->y, 80);
        $this->y = $this->block('Reason: '.$note->reason, 30, $this->y + 25, 80);
        $this->text('Credit amount (including tax): '.$invoice->currency.' '.number_format($note->amount, 2), 30, $this->y + 30, 14, true);
        $this->text('This credit reduces the original invoice charge. It is not a cash refund.', 30, $this->y + 55, 9);
        $this->pages[] = $this->content;

        return $this->encode();
    }

    public function renderReceipt(InvoicePayment $payment): string
    {
        $this->pages = [];
        $this->content = '';
        $invoice = $payment->invoice;
        $this->text('Payment receipt', 30, 45, 20, true);
        $this->y = 90;
        foreach ([
            'Receipt number' => 'RCT-'.$payment->id,
            'Business' => $invoice->business_name,
            'Client' => $invoice->customer_name,
            'Invoice' => $invoice->invoice_number,
            'Payment date' => $payment->paid_on?->format('d M Y') ?? 'Original date unknown',
            'Amount received' => $invoice->currency.' '.number_format($payment->amount, 2),
            'Reference' => $payment->reference ?? 'Not provided',
            'Current invoice balance' => $invoice->currency.' '.$invoice->balanceDue(),
        ] as $label => $value) {
            $this->y = $this->block($label.': '.$value, 30, $this->y, 85);
            $this->y += 16;
        }
        $this->text('This receipt confirms the payment recorded against the invoice.', 30, $this->y + 20, 9);
        $this->pages[] = $this->content;

        return $this->encode();
    }

    private function encode(): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($this->pages as $index => $content) {
            $page = 5 + $index * 2;
            $stream = $page + 1;
            $kids[] = "$page 0 R";
            $content .= 'BT /F1 8 Tf 1 0 0 1 510 25 Tm (Page '.($index + 1).' of '.count($this->pages).") Tj ET\n";
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $stream 0 R >>";
            $objects[$stream] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count '.count($this->pages).' /Kids ['.implode(' ', $kids).'] >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
