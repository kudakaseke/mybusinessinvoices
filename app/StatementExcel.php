<?php

namespace App;

use Illuminate\Support\Collection;
use PharData;
use RuntimeException;

class StatementExcel
{
    /** @param array{clients: Collection, client: ?string, currency: string, entries: Collection, from: ?string, to: ?string, openingBalance: int, charged: int, received: int, credited: int, balance: int} $data */
    public function render(array $data, string $business): string
    {
        $client = $data['client'] ? ($data['clients']->firstWhere('customer_email', $data['client'])?->customer_name ?? $data['client']) : 'All clients';
        $rows = [
            [$business], ['Client statement'], ['Client', $client], ['Currency', $data['currency']],
            ['From', $data['from'] ?? 'Beginning', 'To', $data['to'] ?? 'Latest'],
            ['Opening balance', '', $data['openingBalance']], [],
            ['Date', 'Client', 'Details', 'Invoice charge', 'Payment received', 'Credit note', 'Running balance'],
        ];
        foreach ($data['entries'] as $entry) {
            $rows[] = [$entry['date_label'], $entry['client'], $entry['description'], $entry['charged'], $entry['received'], $entry['credited'], $entry['balance']];
        }
        $rows[] = [];
        foreach (['charged' => 'Invoice charges', 'received' => 'Payments received', 'credited' => 'Credit notes', 'balance' => 'Closing balance'] as $key => $label) {
            $rows[] = [$label, '', $data[$key]];
        }
        $namespace = 'http://schemas.openxmlformats.org/';
        $relationships = $namespace.'package/2006/relationships';
        $office = $namespace.'officeDocument/2006/relationships';
        $spreadsheet = $namespace.'spreadsheetml/2006/main';
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.';
        $files = [
            '[Content_Types].xml' => '<Types xmlns="'.$namespace.'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="'.$mime.'sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="'.$mime.'worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="'.$mime.'styles+xml"/></Types>',
            '_rels/.rels' => '<Relationships xmlns="'.$relationships.'"><Relationship Id="rId1" Type="'.$office.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<workbook xmlns="'.$spreadsheet.'" xmlns:r="'.$office.'"><sheets><sheet name="Statement" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="'.$relationships.'"><Relationship Id="rId1" Type="'.$office.'/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.$office.'/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<styleSheet xmlns="'.$spreadsheet.'"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $this->worksheet($rows, $spreadsheet),
        ];
        $temporary = tempnam(sys_get_temp_dir(), 'statement-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create the Excel export.');
        }
        $path = $temporary.'.zip';
        try {
            $archive = new PharData($path);
            foreach ($files as $name => $content) {
                $archive->addFromString($name, '<?xml version="1.0" encoding="UTF-8"?>'.$content);
            }
            unset($archive);
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Unable to read the Excel export.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            unlink($temporary);
        }
    }

    /** @param list<list<string|int>> $rows */
    private function worksheet(array $rows, string $namespace): string
    {
        $xml = '<worksheet xmlns="'.$namespace.'"><cols><col min="1" max="2" width="28" customWidth="1"/><col min="3" max="3" width="65" customWidth="1"/><col min="4" max="7" width="22" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';
            foreach ($row as $column => $value) {
                $reference = chr(65 + $column).($index + 1);
                if (is_int($value)) {
                    $xml .= '<c r="'.$reference.'" s="1"><v>'.($value < 0 ? '-' : '').DocumentTotals::decimal(abs($value)).'</v></c>';
                } else {
                    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
                    $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }
}
