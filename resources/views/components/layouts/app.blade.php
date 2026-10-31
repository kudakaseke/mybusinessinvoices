<x-layouts.app.sidebar>
    <flux:main>
        {{ $slot }}
    </flux:main>
    @if (auth()->user()->business_profile_completed_at === null)
        <livewire:business-setup />
    @endif
</x-layouts.app.sidebar>
