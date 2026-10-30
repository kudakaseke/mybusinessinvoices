<x-layouts.app title="Calendar">
    <div class="mx-auto max-w-6xl">
        <h1 class="mb-6 text-2xl font-semibold">Calendar</h1>
        <p class="mb-5 text-sm text-zinc-500">Invoices and quotations appear on their issue date, payments on their payment date, expenses on their expense date, and credit notes on their credit date. Select an entry to open its record.</p>
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-[#10261c]" aria-label="Monthly calendar">
            <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                <h2 class="text-xl font-semibold">{{ $month->format('F Y') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <flux:button :href="route('calendar', ['month' => $month->subMonth()->format('Y-m')])" wire:navigate aria-label="Previous month">&larr; Previous</flux:button>
                    <flux:button :href="route('calendar')" wire:navigate>Today</flux:button>
                    <flux:button :href="route('calendar', ['month' => $month->addMonth()->format('Y-m')])" wire:navigate aria-label="Next month">Next &rarr;</flux:button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <div class="grid min-w-[840px] grid-cols-7 border-t border-zinc-200 dark:border-zinc-700">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="py-3 text-center text-xs font-semibold sm:text-sm">{{ $weekday }}</div>
                @endforeach
                @foreach ($start->daysUntil($end->addDay()) as $day)
                    <div @class(['min-h-20 border-t border-zinc-200 p-2 sm:min-h-28 sm:p-3 dark:border-zinc-700', 'text-zinc-400 dark:text-zinc-500' => $day->month !== $month->month])>
                        <time datetime="{{ $day->toDateString() }}" @if($day->toDateString() === now('Africa/Johannesburg')->toDateString()) aria-current="date" @endif @class(['flex size-8 items-center justify-center rounded-full text-sm', 'bg-[#103b26] font-semibold text-white dark:bg-[#cce4ba] dark:text-[#103b26]' => $day->toDateString() === now('Africa/Johannesburg')->toDateString()])>{{ $day->day }}</time>
                        <ul class="mt-2 space-y-2">
                            @foreach($events->get($day->toDateString(), collect()) as $event)
                                <li><a href="{{ $event['url'] }}" wire:navigate @class([
                                    'block break-words rounded-lg p-2 text-xs leading-5 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2',
                                    'bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-200' => $event['type'] === 'invoices',
                                    'bg-violet-50 text-violet-900 dark:bg-violet-950 dark:text-violet-200' => $event['type'] === 'quotations',
                                    'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => $event['type'] === 'payments',
                                    'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $event['type'] === 'expenses',
                                    'bg-rose-50 text-rose-900 dark:bg-rose-950 dark:text-rose-200' => $event['type'] === 'credit-notes',
                                ])><span class="block font-semibold">{{ $event['label'] }}</span><span class="block">{{ $event['number'] }}</span><span class="block">{{ $event['name'] }}</span><span class="block font-medium">{{ $event['currency'] }} {{ number_format($event['amount'], 2) }}</span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            </div>
        </section>
    </div>
</x-layouts.app>
