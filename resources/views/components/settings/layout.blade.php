<div class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-8 dark:border-zinc-700 dark:bg-[#10261c]">
    <div>
        <h2 class="text-xl font-light">{{ $heading ?? '' }}</h2>
        <p class="mt-2 text-sm text-zinc-500">{{ $subheading ?? '' }}</p>
    </div>
    <div class="w-full">
        {{ $slot }}
    </div>
</div>
