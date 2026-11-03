@props(['onDark' => false, 'followTheme' => true])

<div {{ $attributes->merge(['class' => 'flex shrink-0 items-center']) }}>
    @if ($onDark)
        <img src="{{ asset('myit-logo-dark.png') }}" alt="My Invoice Tracker" width="224" height="128" class="h-20 w-56 object-contain mix-blend-screen" />
    @else
        <img src="{{ asset('myit-logo-transparent.png') }}" alt="My Invoice Tracker" width="224" height="128" class="h-20 w-56 object-contain {{ $followTheme ? 'dark:hidden' : '' }}" />
        @if ($followTheme)
            <img src="{{ asset('myit-logo-dark.png') }}" alt="My Invoice Tracker" width="224" height="128" class="hidden h-20 w-56 object-contain mix-blend-screen dark:block" />
        @endif
    @endif
</div>
