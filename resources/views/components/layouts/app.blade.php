@props(['title' => 'mybusinessinvoices'])

<x-layouts.app.sidebar :title="$title">
    <flux:main>
        {{ $slot }}
    </flux:main>
    @if (auth()->user()->business_profile_completed_at === null)
        <livewire:business-setup />
    @endif
</x-layouts.app.sidebar>
