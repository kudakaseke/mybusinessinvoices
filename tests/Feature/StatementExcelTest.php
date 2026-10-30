<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

function excelWorksheet(string $contents): DOMXPath
{
    $temporary = tempnam(sys_get_temp_dir(), 'excel-test-');
    $path = $temporary.'.zip';
    try {
        file_put_contents($path, $contents);
        $archive = new PharData($path);
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $part) {
            $document = new DOMDocument;
            expect($document->loadXML($archive[$part]->getContent(), LIBXML_NONET))->toBeTrue();
        }
        $document = new DOMDocument;
        $document->loadXML($archive['xl/worksheets/sheet1.xml']->getContent(), LIBXML_NONET);
        unset($archive);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        unlink($temporary);
    }
}

test('Excel exports use the selected client currency and dates with numeric balances', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'invoice_number' => 'EXPORT-OWN', 'issue_date' => '2026-10-01', 'currency' => 'USD', 'total' => '100.00', 'status' => 'partial']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00', 'paid_on' => '2026-10-10']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OTHER-CURRENCY', 'customer_email' => 'client@example.com', 'currency' => 'ZWG']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OTHER-CLIENT', 'customer_email' => 'other@example.com']);
    Invoice::factory()->create(['invoice_number' => 'PRIVATE-INVOICE', 'customer_email' => 'client@example.com']);

    $response = $this->actingAs($user)->get('/statements/excel?client=client@example.com&currency=USD&from=2026-10-10&to=2026-10-10')
        ->assertOk()->assertDownload('Client-statement-USD.xlsx')->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $sheet = excelWorksheet($response->getContent());
    expect($sheet->evaluate('string(//s:c[@r="C6"]/s:v)'))->toBe('100.00');
    expect($sheet->evaluate('string(//s:c[@r="E9"]/s:v)'))->toBe('25.00');
    expect($sheet->evaluate('string(//s:c[@r="G9"]/s:v)'))->toBe('75.00');
    $text = $sheet->document->textContent;
    expect($text)->toContain('EXPORT-OWN')->not->toContain('OTHER-CURRENCY')->not->toContain('OTHER-CLIENT')->not->toContain('PRIVATE-INVOICE');
});

test('Excel exports preserve client text as text and include credit notes and negative balances', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => '=1+1', 'total' => '100.00', 'status' => 'settled', 'issue_date' => '2026-10-01']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '100.00', 'paid_on' => '2026-10-02']);
    CreditNote::factory()->for($invoice)->create(['amount' => '20.00', 'credited_on' => '2026-10-03', 'reason' => 'Return & replacement']);

    $response = $this->actingAs($user)->get('/statements/excel')->assertOk()->assertDownload();

    $sheet = excelWorksheet($response->getContent());
    expect($sheet->evaluate('string(//s:c[@r="B9"]/@t)'))->toBe('inlineStr');
    expect($sheet->evaluate('string(//s:c[@r="B9"]/s:is/s:t)'))->toBe('=1+1');
    expect($sheet->evaluate('count(//s:f)'))->toBe(0.0);
    expect($sheet->evaluate('string(//s:c[@r="F11"]/s:v)'))->toBe('20.00');
    expect($sheet->evaluate('string(//s:c[@r="G11"]/s:v)'))->toBe('-20.00');
    expect($sheet->document->textContent)->toContain('Return & replacement');
});

test('Excel exports require authentication validate filters and support empty accounts', function () {
    $this->get('/statements/excel')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/statements/excel?currency=EUR')->assertSessionHasErrors('currency');
    $this->get('/statements/excel?from=2026-10-30&to=2026-10-01')->assertSessionHasErrors('to');
    $response = $this->get('/statements/excel?currency=ZWG')->assertOk()->assertDownload('Client-statement-ZWG.xlsx');

    $sheet = excelWorksheet($response->getContent());
    expect($sheet->document->textContent)->toContain('ZWG')->toContain('Closing balance');
    $this->get('/statements')->assertOk()->assertSee('Generate Excel')->assertSee(route('statements.excel'));
});
