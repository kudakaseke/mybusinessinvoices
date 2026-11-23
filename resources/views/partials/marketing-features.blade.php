<section id="features" class="mx-auto max-w-7xl px-6 py-20 lg:px-12 lg:py-28" aria-labelledby="features-heading">
    <div class="grid gap-12 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.6fr)] lg:gap-20">
        <div>
            <p class="text-xs font-semibold tracking-[.2em] text-[#52735a]">BUILT FOR YOUR BUSINESS</p>
            <{{ $headingTag }} id="features-heading" class="mt-4 text-4xl leading-tight font-light tracking-tight md:text-5xl">Everything you need to stay on top of business.</{{ $headingTag }}>
            <p class="mt-6 max-w-md text-base leading-7 text-[#526457]">Spend less time searching through paperwork and more time doing what you do best. Keep your sales, payments and expenses together in one clear workspace.</p>
            <a href="{{ route('register') }}" class="mt-8 inline-flex items-center rounded-full bg-[#103b26] px-7 py-4 text-sm font-semibold text-white transition hover:bg-[#1b5738] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#103b26]">Get started free <span aria-hidden="true" class="ml-4">&rarr;</span></a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['01', 'Invoices & quotations', 'Create professional documents with your business details, tax and discounts. Turn an accepted quotation into an invoice, then download, print or share it.'],
                ['02', 'Payments & receipts', 'Record full or partial payments, keep a payment history and give clients a receipt. See outstanding balances so you know what is still owed.'],
                ['03', 'Clients & products', 'Keep client contact details and product prices close at hand, ready to use when creating your next invoice or quotation.'],
                ['04', 'Expenses', 'Record everyday business spending and attach receipts, so your expenses are easier to find and review.'],
                ['05', 'Statements & exports', 'Review client invoices, payments and balances by date and currency. Download statements as PDF or Excel files when you need them.'],
                ['06', 'Credit notes & refunds', 'Keep a record of returns, invoice adjustments and refunds alongside the original invoice.'],
                ['07', 'Calendar & reports', 'See business activity on a calendar and compare invoices, payments and expenses in your reports. Review totals separately by currency.'],
                ['08', 'Payment reminders', 'Send reminders for overdue invoices or enable weekly automatic reminders to help you follow up with clients.'],
            ] as [$number, $heading, $description])
                <article class="rounded-2xl border border-[#d8e3d4] bg-white p-6 sm:p-7">
                    <span class="text-xs font-semibold tracking-[.12em] text-[#6f8b72]">{{ $number }} /</span>
                    <h3 class="mt-5 text-xl font-semibold tracking-tight text-[#103b26]">{{ $heading }}</h3>
                    <p class="mt-3 text-sm leading-7 text-[#526457]">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
