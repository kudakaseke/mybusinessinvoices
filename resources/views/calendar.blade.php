<x-layouts.app title="Calendar">
    <div class="mx-auto max-w-5xl">
        <h1 class="mb-3 text-2xl font-semibold">Calendar</h1>
        <p class="mb-3 text-xs text-zinc-500">Each date shows combined totals by currency. Select a summary to view that day's statement.</p>
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-[#10261c]" aria-label="Monthly calendar">
            <div class="flex flex-wrap items-center justify-between gap-3 p-3">
                <h2 class="text-lg font-semibold">{{ $month->format('F Y') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" :href="route('calendar', ['month' => $month->subMonth()->format('Y-m')])" wire:navigate aria-label="Previous month">&larr; Previous</flux:button>
                    <flux:button size="sm" :href="route('calendar')" wire:navigate>Today</flux:button>
                    <flux:button size="sm" :href="route('calendar', ['month' => $month->addMonth()->format('Y-m')])" wire:navigate aria-label="Next month">Next &rarr;</flux:button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <div class="grid min-w-[700px] grid-cols-7 border-t border-zinc-200 dark:border-zinc-700">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="py-2 text-center text-xs font-semibold">{{ $weekday }}</div>
                @endforeach
                @foreach ($start->daysUntil($end->addDay()) as $day)
                    <div @class(['flex h-24 min-w-0 flex-col overflow-hidden border-t border-zinc-200 p-1.5 sm:h-28 dark:border-zinc-700', 'text-zinc-400 dark:text-zinc-500' => $day->month !== $month->month])>
                        <time datetime="{{ $day->toDateString() }}" @if($day->toDateString() === now('Africa/Johannesburg')->toDateString()) aria-current="date" @endif @class(['flex size-6 shrink-0 items-center justify-center rounded-full text-xs', 'bg-[#103b26] font-semibold text-white dark:bg-[#cce4ba] dark:text-[#103b26]' => $day->toDateString() === now('Africa/Johannesburg')->toDateString()])>{{ $day->day }}</time>
                        <div class="mt-1 min-h-0 flex-1 space-y-2 overflow-y-auto text-[10px] leading-tight" aria-label="Totals for {{ $day->format('d M Y') }}">
                            @foreach($dailyTotals->get($day->toDateString(), collect()) as $total)
                                <a class="block space-y-0.5 hover:underline" href="{{ route('statements', ['from' => $day->toDateString(), 'to' => $day->toDateString(), 'currency' => $total['currency']]) }}" wire:navigate>
                                    <span class="block font-semibold">{{ $total['currency'] }}</span>
                                    @foreach(['invoiced' => 'Total Invoice', 'received' => 'Total Payments Received', 'expenses' => 'Total Expenses', 'credited' => 'Total Credit Notes'] as $field => $label)
                                        <span @class(['block', 'text-red-600 dark:text-red-400' => $field === 'expenses'])>{{ $label }}: <strong>{{ number_format($total[$field] / 100, 2) }}</strong></span>
                                    @endforeach
                                    @if($total['quoted'] > 0)
                                        <span class="block">Total Quotations: <strong>{{ number_format($total['quoted'] / 100, 2) }}</strong></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            </div>
        </section>
    </div>
</x-layouts.app>
