@props([])

<div {{ $attributes->merge(['class' => 'sticky top-0 z-20 mb-6']) }}>
    <div class="rounded-2xl border border-line bg-white/95 backdrop-blur-md shadow-sm overflow-hidden">
        <div class="px-3 sm:px-4 py-3 md:px-5 md:py-3.5 border-b border-line bg-surface/95 backdrop-blur-sm">
            <div class="-mx-1 px-1 overflow-x-auto overscroll-x-contain md:overflow-visible md:mx-0 md:px-0 [scrollbar-width:thin]">
                <div class="flex flex-nowrap md:flex-wrap md:items-center gap-x-5 gap-y-2 text-sm w-max max-w-none md:w-full md:max-w-full">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @isset($tabs)
            {{ $tabs }}
        @endisset
    </div>
</div>
