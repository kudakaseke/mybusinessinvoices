<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-[#f5f7f4] text-[#13291f] dark:bg-[#0b1712] dark:text-zinc-100">
<flux:sidebar sticky stashable class="business-sidebar border-r border-white/10 bg-[#0c2118]! text-white! print:hidden">
<flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
<a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2 py-5" wire:navigate><span class="flex size-10 items-center justify-center rounded-xl bg-[#cce4ba] text-xl font-semibold text-[#0c2118]">m.</span><span class="text-lg tracking-tight">mybusiness<span class="block text-xs tracking-widest text-white/50">INVOICES</span></span></a>
<flux:navlist variant="outline" class="mt-7">
<flux:navlist.group heading="Main" class="grid">
@foreach (['dashboard' => 'Dashboard', 'analytics' => 'Analytics', 'reports' => 'Reports', 'calendar' => 'Calendar'] as $name => $label)
<flux:navlist.item :href="route($name)" :current="request()->routeIs($name)" wire:navigate>{{ $label }}</flux:navlist.item>
@endforeach
</flux:navlist.group>
<flux:navlist.group heading="Finance" class="mt-6 grid">
<flux:navlist.item :href="route('drafts.index')" :current="request()->routeIs('drafts.*')" wire:navigate>Saved drafts</flux:navlist.item>
@foreach (['invoices' => 'Invoices', 'quotations' => 'Quotations'] as $kind => $label)
<flux:navlist.item :href="route('documents.index', $kind)" :current="request()->is($kind.'*')" wire:navigate>{{ $label }}</flux:navlist.item>
@endforeach
<flux:navlist.item :href="route('records.index', 'expenses')" :current="request()->is('expenses*')" wire:navigate>Expenses</flux:navlist.item>
<flux:navlist.item :href="route('statements')" :current="request()->routeIs('statements')" wire:navigate>Statements</flux:navlist.item>
<flux:navlist.item :href="route('credit-notes.index')" :current="request()->routeIs('credit-notes.*')" wire:navigate>Credit notes</flux:navlist.item>
</flux:navlist.group>
<flux:navlist.group heading="Manage" class="mt-6 grid">
<flux:navlist.item :href="route('activity')" :current="request()->routeIs('activity')" wire:navigate>Activity history</flux:navlist.item>
<flux:navlist.item :href="route('records.index', 'products')" :current="request()->is('products*')" wire:navigate>Product</flux:navlist.item>
<flux:navlist.item :href="route('records.index', 'clients')" :current="request()->is('clients*')" wire:navigate>Client</flux:navlist.item>
<flux:navlist.item :href="route('settings')" :current="request()->is('settings*')" wire:navigate>Settings</flux:navlist.item>
</flux:navlist.group>
</flux:navlist>
<flux:spacer />
<div class="mt-8 border-t border-white/15 px-2 pt-5"><p class="truncate text-sm">{{ auth()->user()->business_name ?? auth()->user()->name }}</p><p class="mt-1 truncate text-xs text-white/50">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-xs text-white/70 hover:text-white" type="submit">Sign out ↗</button></form></div>
</flux:sidebar>
<flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-[#10261c] print:hidden"><flux:sidebar.toggle icon="bars-2" /><flux:spacer /><span>mybusinessinvoices</span></flux:header>
{{ $slot }}
@fluxScripts
</body></html>
