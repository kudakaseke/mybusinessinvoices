<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{ $title ?? 'mybusinessinvoices' }}</title>
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-mybi.png') }}" />
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}" />
<meta name="theme-color" content="#0b2117" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
