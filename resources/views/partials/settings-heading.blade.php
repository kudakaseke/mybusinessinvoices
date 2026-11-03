<h1 class="mb-6 text-4xl font-light">Settings</h1>
@php($currentSettingsRoute = $activeSettingsRoute ?? request()->route()?->getName())
<nav class="mb-8 flex flex-wrap gap-5 text-sm" aria-label="Settings">
    @foreach(['settings' => 'Business', 'settings.profile' => 'Profile', 'settings.password' => 'Password', 'settings.appearance' => 'Appearance'] as $settingsRoute => $label)
        <a href="{{ route($settingsRoute) }}" @class(['font-semibold' => $currentSettingsRoute === $settingsRoute]) @if($currentSettingsRoute === $settingsRoute) aria-current="page" @endif wire:navigate>{{ $label }}</a>
    @endforeach
</nav>
