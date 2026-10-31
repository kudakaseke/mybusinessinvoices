<section id="pricing" class="mx-auto max-w-7xl px-6 py-20 lg:px-12" aria-labelledby="pricing-heading">
        <p class="text-xs tracking-[.2em]">PRICING</p>
        <{{ $headingTag }} id="pricing-heading" class="mt-4 text-4xl font-light tracking-tight md:text-5xl">Start free. Grow with Annual.</{{ $headingTag }}>
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
                <a href="{{ route('register') }}" class="mt-auto w-fit rounded-full border border-[#0b2117] px-8 py-4 text-sm">Start free</a>
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
