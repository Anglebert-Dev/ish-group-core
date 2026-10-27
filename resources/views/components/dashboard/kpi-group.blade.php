@props(['title', 'open' => true])

<details class="group/kpi w-full rounded-2xl border border-line bg-white overflow-hidden" @if ($open) open @endif>
    <summary class="flex items-center justify-between gap-4 px-4 sm:px-5 py-3 sm:py-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
        <div class="flex items-center gap-3 min-w-0">
            @isset($icon)
                <span class="flex-shrink-0 p-2 rounded-lg bg-brand-light text-brand">
                    {{ $icon }}
                </span>
            @endisset
            <h2 class="text-sm sm:text-base font-semibold text-ink truncate">{{ $title }}</h2>
        </div>
        <span class="flex-shrink-0 p-1.5 rounded-lg border border-brand/30 text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 hidden group-open/kpi:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 group-open/kpi:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </summary>
    <div class="px-4 sm:px-5 pb-4 sm:pb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
            {{ $slot }}
        </div>
    </div>
</details>
