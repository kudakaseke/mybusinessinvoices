<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $documentLabel }} {{ $number }}</title></head>
<body>
    <p>Hello {{ $client }},</p>
    <p>{{ $reminder ? 'This is a friendly reminder about your overdue invoice.' : 'Please find your '.$documentLabel.' attached as a PDF.' }}</p>
    <p>{{ $documentLabel }}: <strong>{{ $number }}</strong></p>
    <p>{{ $documentLabel === 'Invoice' ? 'Remaining balance' : 'Quoted total' }}: <strong>{{ $currency }} {{ number_format((float) $amount, 2) }}</strong></p>
    <p>If you have already arranged payment, please contact {{ $business }} to reconcile your account.</p>
    <p>Thank you,<br>{{ $business }}</p>
</body></html>
