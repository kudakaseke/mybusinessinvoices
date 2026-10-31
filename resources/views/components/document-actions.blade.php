@props(['document', 'kind'])
@php
$label = $kind === 'invoices' ? 'Invoice' : 'Quotation';
$number = $kind === 'invoices' ? $document->invoice_number : $document->quotation_number;
$message = $label.' '.$number."\n".$document->business_name."\nFor: ".$document->customer_name."\nTotal: ".$document->currency.' '.number_format($document->total,2)."\n".($kind === 'invoices' ? 'Due: ' : 'Valid until: ').$document->due_date->format('F j, Y');
$emailUrl = 'mailto:'.rawurlencode($document->customer_email).'?subject='.rawurlencode($label.' '.$number).'&body='.rawurlencode($message);
$phone = preg_replace('/[^0-9]/', '', $document->customer_phone ?? '');
$whatsappUrl = 'https://wa.me/'.($phone ?: '').'?text='.rawurlencode($message);
@endphp
<div {{ $attributes->class(['flex flex-wrap justify-center gap-3']) }}>
    <flux:button type="button" onclick="window.print()">Print</flux:button>
    <flux:button :href="route('documents.download', [$kind, $document->id])" variant="primary">Download PDF</flux:button>
    <form method="POST" action="{{ route('documents.email', [$kind, $document->id]) }}">@csrf<flux:button type="submit">Email PDF</flux:button></form>
    <flux:button :href="$emailUrl">Email draft</flux:button>
    <flux:button :href="$whatsappUrl" target="_blank" rel="noopener noreferrer">WhatsApp</flux:button>
</div>
