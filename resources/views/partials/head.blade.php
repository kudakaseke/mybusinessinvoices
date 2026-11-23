<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{ $title ?? 'My Invoice Tracker' }}</title>
<link rel="icon" type="image/png" href="{{ asset('myit-app-icon.png') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('myit-app-icon.png') }}" />
@if(request()->routeIs('home', 'features', 'pricing'))
    @php
        $pageDescription = match (request()->route()->getName()) {
            'features' => 'Explore invoicing, quotations, receipts and business reporting features.',
            'pricing' => 'Explore pricing for My Invoice Tracker.',
            default => 'Create invoices, track payments and manage your business records with My Invoice Tracker.',
        };
        $canonicalUrl = route(request()->route()->getName());
    @endphp
    <meta name="description" content="{{ $pageDescription }}" />
    <link rel="canonical" href="{{ $canonicalUrl }}" />
    <meta property="og:title" content="{{ $title ?? 'My Invoice Tracker' }}" />
    <meta property="og:description" content="{{ $pageDescription }}" />
    <meta property="og:url" content="{{ $canonicalUrl }}" />
    <meta property="og:type" content="website" />
@endif
<meta name="theme-color" content="#0b2117" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
