@props(['title' => 'mybusinessinvoices'])
<!DOCTYPE html>
<html lang="en" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    @include('partials.head')
</head>
<body class="bg-[#f6f8f3] font-sans text-[#10281d]">
    <div class="bg-[#0b2117] text-[#f5f8ef]">
        <header class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-8 lg:px-12">
            <a href="{{ route('home') }}" class="flex items-center gap-3 text-lg">
                <span class="rounded-xl bg-[#cbe7b4] px-3 py-2 text-xl font-bold text-[#0b2117]">m.</span>mybusinessinvoices
            </a>
            <nav class="flex flex-wrap items-center gap-5 text-sm md:gap-8" aria-label="Main navigation">
                @foreach (['home' => 'Home', 'features' => 'Features', 'pricing' => 'Pricing'] as $page => $label)
                    <a href="{{ $page === 'home' ? route('home') : route('home').'#'.$page }}" @if(request()->routeIs($page)) aria-current="page" @endif class="{{ request()->routeIs($page) ? 'font-semibold text-[#cbe7b4]' : '' }}">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-[#cbe7b4] px-6 py-3 text-[#0b2117]">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-full border border-white/35 px-6 py-3">Sign up</a>
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
