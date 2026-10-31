<section id="features" class="mx-auto max-w-7xl px-6 py-20 lg:px-12">
        <p class="text-xs tracking-[.2em]">FEATURES</p>
        <{{ $headingTag }} class="mt-4 text-4xl font-light tracking-tight md:text-5xl">Everything has its place.</{{ $headingTag }}>
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
                    <a href="{{ route('register') }}" class="mt-auto text-sm">Get started</a>
                </article>
            @endforeach
        </div>
    </section>
