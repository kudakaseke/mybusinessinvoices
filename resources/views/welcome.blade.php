<!DOCTYPE html><html lang="en"><head>@include('partials.head')</head>
<body class="bg-[#f6f8f3] font-sans text-[#10281d]">
<div class="bg-[#0b2117] text-[#f5f8ef]"><header class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-8 lg:px-12"><a href="{{ route('home') }}" class="flex items-center gap-3 text-lg"><span class="rounded-xl bg-[#cbe7b4] px-3 py-2 text-xl font-bold text-[#0b2117]">m.</span>mybusinessinvoices</a><nav class="flex flex-wrap items-center gap-5 text-sm md:gap-8" aria-label="Main navigation"><a href="{{ route('home') }}">Home</a><a href="#features">Features</a><a href="#pricing">Pricing</a>@auth<a href="{{ route('dashboard') }}" class="rounded-full bg-[#cbe7b4] px-6 py-3 text-[#0b2117]">Dashboard ↗</a>@else<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}" class="rounded-full border border-white/35 px-6 py-3">Sign up ↗</a>@endauth</nav></header>
<section class="mx-auto max-w-7xl px-6 pb-20 pt-16 lg:px-12 lg:pb-28 lg:pt-24"><p class="mb-7 text-xs tracking-[.25em] text-[#cbe7b4]">YOUR BUSINESS. IN GOOD ORDER.</p><h1 class="max-w-5xl text-5xl leading-[1.08] font-light tracking-[-.045em] md:text-7xl lg:text-[100px]">Less paperwork.<br><span class="text-[#cbe7b4]">More possibility.</span></h1><div class="mt-10 flex flex-col justify-between gap-10 md:flex-row md:items-end"><p class="max-w-xl text-lg leading-relaxed text-white/70 md:text-xl">Invoices, quotations, and expenses.<br>One clear view of the business you’re building.</p><a href="{{ route('register') }}" class="inline-flex w-fit items-center gap-14 rounded-full bg-[#f4f7ee] px-8 py-5 text-[#0b2117]">Start invoicing <span>↗</span></a></div></section></div>
<main>
    <section id="features" class="mx-auto max-w-7xl px-6 py-20 lg:px-12">
        <p class="text-xs tracking-[.2em]">FEATURES</p>
        <h2 class="mt-4 text-4xl font-light tracking-tight md:text-5xl">Everything has its place.</h2>
        <p class="mt-5 mb-12 max-w-2xl text-sm leading-7 text-[#667466]">From your first quotation to the final payment, keep your documents, clients, and day-to-day spending in one workspace.</p>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['01', 'Invoices & quotations', 'Create professional documents with your business details, line items, tax, and discounts. Print or download PDFs, with email and WhatsApp sharing shortcuts.'],
                ['02', 'Payments & credit notes', 'Record full or partial payments, add further payments later, and keep a payment history. Issue credit notes for returns or invoice adjustments.'],
                ['03', 'Clients & products', 'Automatically save invoice clients and keep their contact details, your products, and prices ready for the next document.'],
                ['04', 'Statements & exports', 'Review invoices, payments, credit notes, and running balances by client, date, and currency. Track business expenses separately and export statements to PDF or Excel.'],
                ['05', 'Daily expenses & calendar', 'Record day-to-day spending and view daily invoice, payment, expense, and credit note totals in the calendar, with quotations shown on their issue dates.'],
                ['06', 'Analytics & reports', 'Compare invoice totals, payments received, and expenses on a line graph. Review reports by currency without mixing separate currency balances.'],
            ] as [$number, $heading, $description])
                <article class="flex flex-col gap-8 rounded-tr-[70px] rounded-bl-[70px] border border-[#cfdbcb] bg-white p-8">
                    <span class="text-sm text-[#788b72]">{{ $number }} /</span>
                    <div>
                        <h3 class="text-2xl font-light">{{ $heading }}</h3>
                        <p class="mt-5 text-sm leading-7 text-[#667466]">{{ $description }}</p>
                    </div>
                    <a href="{{ route('register') }}" class="mt-auto text-sm">Get started ↗</a>
                </article>
            @endforeach
        </div>
    </section>
    <section id="pricing" class="mx-auto max-w-7xl px-6 pb-20 lg:px-12" aria-labelledby="pricing-heading">
        <p class="text-xs tracking-[.2em]">PRICING</p>
        <h2 id="pricing-heading" class="mt-4 text-4xl font-light tracking-tight md:text-5xl">Start free. Grow with Annual.</h2>
        <p class="mt-5 mb-10 max-w-2xl text-sm leading-7 text-[#667466]">Simple options for keeping your business paperwork in order. Prices are in USD.</p>
        <div class="grid gap-6 md:grid-cols-2">
            <article class="flex flex-col gap-8 rounded-tl-[60px] rounded-br-[60px] border border-[#cfdbcb] bg-white p-8 md:p-10">
                <div>
                    <h3 class="text-2xl font-light">Free</h3>
                    <p class="mt-4"><span class="text-5xl font-light">$0</span><span class="ml-2 text-sm text-[#667466]">to get started</span></p>
                    <p class="mt-5 text-sm leading-7 text-[#667466]">The essentials for creating your first business documents.</p>
                </div>
                <ul class="space-y-3 text-sm">
                    <li>Invoices and quotations</li>
                    <li>Client and product records</li>
                    <li>Tax and discount calculations</li>
                    <li>Print and PDF downloads</li>
                </ul>
                <a href="{{ route('register') }}" class="mt-auto w-fit rounded-full border border-[#0b2117] px-8 py-4 text-sm">Start free ↗</a>
            </article>
            <article class="flex flex-col gap-8 rounded-tl-[60px] rounded-br-[60px] bg-[#dcebd3] p-8 md:p-10">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-2xl font-light">Annual</h3>
                        <span class="rounded-full bg-[#0b2117] px-4 py-2 text-xs font-semibold text-white">Coming soon</span>
                    </div>
                    <p class="mt-4"><span class="text-5xl font-light">$15</span><span class="ml-2 text-sm text-[#667466]">/ year</span></p>
                    <p class="mt-5 text-sm leading-7 text-[#667466]">Your complete workspace for tracking sales, payments, and spending.</p>
                </div>
                <ul class="space-y-3 text-sm">
                    <li>Everything listed in Free</li>
                    <li>Partial payments and payment history</li>
                    <li>Credit notes for returns and adjustments</li>
                    <li>Client statements with date and currency filters</li>
                    <li>PDF and Excel statement exports</li>
                    <li>Day-to-day expense tracking</li>
                    <li>Calendar with daily activity totals</li>
                    <li>Invoice, payment, and expense analytics and reports</li>
                </ul>
                <button type="button" disabled class="mt-auto w-fit cursor-not-allowed rounded-full bg-[#0b2117] px-8 py-4 text-sm text-white opacity-60">Coming soon</button>
            </article>
        </div>
    </section>
</main><footer class="mx-auto flex max-w-7xl flex-wrap justify-between gap-5 border-t border-[#d9e1d4] px-6 py-8 text-xs text-[#667466] lg:px-12"><span>mybusinessinvoices</span><span>Invoices · Payments · Expenses · Reports</span></footer></body></html>
