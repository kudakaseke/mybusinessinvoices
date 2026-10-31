<x-layouts.marketing title="Home | mybusinessinvoices">
    <section class="relative isolate overflow-hidden bg-[#0b2117] text-[#f5f8ef]" aria-labelledby="home-heading">
        <img src="{{ asset('accounting-hero.jpg') }}" alt="" width="1000" height="669" fetchpriority="high" class="absolute inset-0 -z-20 h-full w-full object-cover object-right opacity-80 saturate-75" />
        <div aria-hidden="true" class="absolute inset-0 -z-10" style="background: linear-gradient(90deg, #0b2117 0%, rgba(11, 33, 23, .94) 28%, rgba(11, 33, 23, .68) 58%, rgba(11, 33, 23, .2) 100%), linear-gradient(0deg, #07180f 0%, transparent 45%, rgba(7, 24, 15, .25) 100%);"></div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[#07180f]/25 sm:bg-transparent"></div>
        <div class="mx-auto flex min-h-[640px] max-w-7xl flex-col justify-center px-6 py-16 sm:py-20 lg:min-h-[740px] lg:px-12 lg:py-24">
            <p class="mb-7 text-xs font-medium tracking-[.25em] text-[#cbe7b4]">YOUR BUSINESS. IN GOOD ORDER.</p>
            <h1 id="home-heading" class="max-w-5xl text-5xl leading-[1.08] font-light tracking-[-.045em] drop-shadow-lg sm:text-7xl lg:text-[100px]">
                Less paperwork.<br>
                <span class="text-[#cbe7b4]">More possibility.</span>
            </h1>
            <div aria-hidden="true" class="my-8 h-px w-24 bg-[#cbe7b4]/60"></div>
            <p class="max-w-xl text-lg leading-relaxed text-white/90 drop-shadow-md sm:text-xl">
                Invoices, quotations, and expenses.<br>
                One clear view of the business you’re building.
            </p>
            <a href="{{ route('register') }}" class="mt-9 inline-flex w-fit items-center gap-10 rounded-full bg-[#f4f7ee] px-8 py-5 text-[#0b2117] shadow-xl shadow-black/25 transition hover:bg-[#cbe7b4] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#cbe7b4]">
                Start invoicing
            </a>
            <a href="#features" class="mt-12 inline-flex w-fit items-center gap-5 text-xs font-medium tracking-[.2em] text-[#cbe7b4] transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#cbe7b4]">
                FEATURES
            </a>
        </div>
    </section>
    @include('partials.marketing-features', ['headingTag' => 'h2'])
    @include('partials.marketing-pricing', ['headingTag' => 'h2'])
</x-layouts.marketing>
