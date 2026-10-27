@props(['action', 'open' => false])

{{-- Collapsible "Filters" panel above a list. --}}
<details class="group bg-white border border-line rounded-xl mb-4" @if ($open) open @endif>
    <summary class="flex items-center gap-2 px-5 py-3.5 cursor-pointer list-none text-brand font-semibold [&::-webkit-details-marker]:hidden">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z"/></svg>
        {{ __('body.filters') }}
    </summary>
    <form method="GET" action="{{ $action }}" class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        {{ $slot }}
        <div class="flex gap-2">
            <x-common.button type="submit" size="sm">{{ __('body.apply') }}</x-common.button>
            <x-common.button href="{{ $action }}" variant="secondary" size="sm">{{ __('body.reset') }}</x-common.button>
        </div>
    </form>
</details>
