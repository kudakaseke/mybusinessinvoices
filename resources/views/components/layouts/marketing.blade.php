@props(['title' => 'mybusinessinvoices'])
<!DOCTYPE html>
<html lang="en" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    @include('partials.head')
</head>
<body class="bg-[#f6f8f3] font-sans text-[#10281d]">
    <div class="border-b border-[#d9e1d4] bg-white text-[#0b2117]">
        <header class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-4 lg:px-12">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="My Business Invoices home">
                <img src="{{ asset('mybi-logo-white-header.png') }}" alt="My Business Invoices" width="224" height="80" class="h-20 w-56 object-cover" />
            </a>
            <nav class="flex flex-wrap items-center gap-5 text-sm md:gap-8" aria-label="Main navigation">
                @foreach (['home' => 'Home', 'features' => 'Features', 'pricing' => 'Pricing'] as $page => $label)
                    <a href="{{ $page === 'home' ? route('home') : route('home').'#'.$page }}" @if(request()->routeIs($page)) aria-current="page" @endif class="{{ request()->routeIs($page) ? 'font-semibold text-[#103b26]' : '' }}">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-[#103b26] px-6 py-3 text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-[#103b26] px-6 py-3 text-white">Sign up</a>
                @endauth
            </nav>
        </header>
    </div>
    <main>{{ $slot }}</main>
    <footer class="mx-auto flex max-w-7xl flex-wrap justify-between gap-5 border-t border-[#d9e1d4] px-6 py-8 text-xs text-[#667466] lg:px-12">
        <span>mybusinessinvoices</span>
        <span>Invoices · Payments · Expenses · Reports</span>
    </footer>
</body>
</html>
