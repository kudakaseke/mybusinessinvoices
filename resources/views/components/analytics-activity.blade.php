@props(['activity'])
@php
    $weeks = $activity['weeks'];
    $maximum = max(100, ...array_column($weeks, 'invoiced'), ...array_column($weeks, 'received'), ...array_column($weeks, 'expenses'));
    $step = (int) ceil($maximum / 4);
    $ceiling = $step * 4;
    $points = ['invoiced' => [], 'received' => [], 'expenses' => []];
    foreach ($weeks as $index => $week) {
        foreach (array_keys($points) as $field) {
            $points[$field][] = round(76 + $index * (774 / 11), 2).','.round(248 - $week[$field] / $ceiling * 220, 2);
        }
    }
    $axisLabel = fn (int $cents): string => $cents >= 100000000 ? number_format($cents / 100000000, 1).'m' : ($cents >= 100000 ? number_format($cents / 100000, 1).'k' : number_format($cents / 100, 2));
@endphp
<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-[#10261c]" aria-labelledby="activity-heading">
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div><h2 id="activity-heading" class="text-xl font-semibold">Invoices, payments &amp; expenses</h2><p class="mt-1 text-sm text-zinc-500">Last 12 weeks · Weekly totals in {{ $activity['currency'] }}</p></div>
        <form method="GET" action="{{ route('analytics') }}" x-data class="flex items-end gap-2">
            <flux:select name="currency" label="Currency" x-on:change="$el.form.requestSubmit()">
                @foreach(['USD' => 'USD', 'ZWG' => 'ZiG (ZWG)'] as $value => $label)<option value="{{ $value }}" @selected($activity['currency'] === $value)>{{ $label }}</option>@endforeach
            </flux:select>
            <noscript><button type="submit">Update</button></noscript>
        </form>
    </div>
    <div class="mt-7 grid gap-5 border-b border-zinc-200 pb-6 sm:grid-cols-3 dark:border-zinc-700">
        <div><p class="text-sm text-zinc-500">Total invoiced in range</p><p class="mt-2 text-2xl font-semibold">{{ $activity['currency'] }} {{ number_format($activity['invoiced'] / 100, 2) }}</p></div>
        <div><p class="text-sm text-zinc-500">Total payments in range</p><p class="mt-2 text-2xl font-semibold">{{ $activity['currency'] }} {{ number_format($activity['received'] / 100, 2) }}</p></div>
        <div><p class="text-sm text-zinc-500">Total expenses in range</p><p class="mt-2 text-2xl font-semibold text-red-600 dark:text-red-300">{{ $activity['currency'] }} {{ number_format($activity['expenses'] / 100, 2) }}</p></div>
    </div>
    <div class="mt-5 flex flex-wrap gap-5 text-sm">
        <span class="flex items-center gap-2"><span class="h-1 w-6 rounded bg-emerald-600 dark:bg-emerald-400" aria-hidden="true"></span>Total invoices</span>
        <span class="flex items-center gap-2"><span class="w-6 border-t-2 border-dashed border-orange-600 dark:border-orange-400" aria-hidden="true"></span>Total payments received</span>
        <span class="flex items-center gap-2"><span class="w-6 border-t-2 border-dotted border-red-600 dark:border-red-400" aria-hidden="true"></span>Total expenses</span>
    </div>
    <div class="mt-3 overflow-x-auto">
        <svg viewBox="0 0 900 295" class="min-w-[600px] w-full" role="img" aria-labelledby="activity-chart-title activity-chart-description">
            <title id="activity-chart-title">Weekly invoices, payments received and expenses in {{ $activity['currency'] }}</title>
            <desc id="activity-chart-description">Three lines compare invoice amounts, actual payments and expenses each week. Exact values are available in the weekly amounts table below.</desc>
            @for($tick = 0; $tick <= 4; $tick++)
                <line x1="76" y1="{{ 248 - $tick * 55 }}" x2="850" y2="{{ 248 - $tick * 55 }}" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-dasharray="4 5" />
                <text x="64" y="{{ 252 - $tick * 55 }}" text-anchor="end" class="fill-zinc-500 dark:fill-zinc-400" font-size="12">{{ $axisLabel($tick * $step) }}</text>
            @endfor
            <polyline points="{{ implode(' ', $points['invoiced']) }}" fill="none" class="stroke-emerald-600 dark:stroke-emerald-400" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <polyline points="{{ implode(' ', $points['received']) }}" fill="none" class="stroke-orange-600 dark:stroke-orange-400" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="7 5" />
            <polyline points="{{ implode(' ', $points['expenses']) }}" fill="none" class="stroke-red-600 dark:stroke-red-400" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="2 5" />
            @foreach($weeks as $index => $week)
                <g><title>Week of {{ $week['label'] }}: invoices {{ $activity['currency'] }} {{ number_format($week['invoiced'] / 100, 2) }}; payments {{ $activity['currency'] }} {{ number_format($week['received'] / 100, 2) }}; expenses {{ $activity['currency'] }} {{ number_format($week['expenses'] / 100, 2) }}</title>
                    <circle cx="{{ round(76 + $index * (774 / 11), 2) }}" cy="{{ round(248 - $week['invoiced'] / $ceiling * 220, 2) }}" r="4" class="fill-emerald-600 dark:fill-emerald-400" />
                    <circle cx="{{ round(76 + $index * (774 / 11), 2) }}" cy="{{ round(248 - $week['received'] / $ceiling * 220, 2) }}" r="4" class="fill-orange-600 dark:fill-orange-400" />
                    <circle cx="{{ round(76 + $index * (774 / 11), 2) }}" cy="{{ round(248 - $week['expenses'] / $ceiling * 220, 2) }}" r="4" class="fill-red-600 dark:fill-red-400" />
                </g>
                @if($index % 2 === 0 || $index === 11)<text x="{{ round(76 + $index * (774 / 11), 2) }}" y="278" text-anchor="middle" class="fill-zinc-500 dark:fill-zinc-400" font-size="12">{{ $week['label'] }}</text>@endif
            @endforeach
        </svg>
    </div>
    @if($activity['invoiced'] === 0 && $activity['received'] === 0 && $activity['expenses'] === 0)<p class="mt-2 text-sm text-zinc-500">No invoice, payment or expense activity in this currency during the last 12 weeks.</p>@endif
    @if($activity['undatedPayments'])<p class="mt-2 text-xs text-zinc-500">Payments without a recorded date are not included in this date-based graph.</p>@endif
    <details class="mt-4 text-sm"><summary class="cursor-pointer text-zinc-500">View weekly amounts</summary><div class="mt-4 overflow-x-auto"><table class="w-full text-left"><thead><tr><th class="pb-3">Week starting</th><th class="text-right">Total invoices ({{ $activity['currency'] }})</th><th class="text-right">Total payments ({{ $activity['currency'] }})</th><th class="text-right">Total expenses ({{ $activity['currency'] }})</th></tr></thead><tbody>
        @foreach($weeks as $week)<tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="py-3">{{ $week['date'] }}</td><td class="text-right">{{ number_format($week['invoiced'] / 100, 2) }}</td><td class="text-right">{{ number_format($week['received'] / 100, 2) }}</td><td class="text-right">{{ number_format($week['expenses'] / 100, 2) }}</td></tr>@endforeach
    </tbody></table></div></details>
</section>
