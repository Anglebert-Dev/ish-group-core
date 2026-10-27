@props([
    'vehicles',
    'categories' => collect(),
    'types' => collect(),
    'totalCount' => null,
])

@php
    $count = $totalCount ?? $vehicles->count();
@endphp

<section class="bg-[#f4f6f8] border-b border-line" data-vehicle-carousel>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">

        {{-- Quick filters --}}
        <div class="flex flex-wrap items-center gap-2 mb-8">
            <button type="button" data-filter-all class="vehicle-filter-pill is-active">
                {{ __('body.filter_all') }}
            </button>

            <div class="relative" data-filter-menu>
                <button type="button" data-filter-toggle="type" class="vehicle-filter-pill">
                    {{ __('body.filter_type') }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div data-filter-panel="type" class="vehicle-filter-panel hidden">
                    <button type="button" data-filter-type="" class="vehicle-filter-option is-active">{{ __('body.filter_all') }}</button>
                    @foreach ($types as $type)
                        <button type="button" data-filter-type="{{ $type->value }}" class="vehicle-filter-option">{{ $type->label() }}</button>
                    @endforeach
                </div>
            </div>

            <div class="relative" data-filter-menu>
                <button type="button" data-filter-toggle="category" class="vehicle-filter-pill">
                    {{ __('body.filter_category') }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div data-filter-panel="category" class="vehicle-filter-panel hidden">
                    <button type="button" data-filter-category="" class="vehicle-filter-option is-active">{{ __('body.filter_all') }}</button>
                    @foreach ($categories as $category)
                        <button type="button" data-filter-category="{{ $category->id }}" class="vehicle-filter-option">{{ $category->name }}</button>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('vehicles.index') }}" class="vehicle-filter-pill sm:ml-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                {{ __('body.filters') }}
            </a>
        </div>

        {{-- Heading --}}
        <div class="mb-6">
            <h2 class="font-display font-bold text-2xl sm:text-3xl text-ink">{{ __('body.featured_cars') }}</h2>
            <p class="text-sm text-muted mt-1" data-match-label>
                {{ __('body.matching_cars_swipe', ['count' => $count]) }}
            </p>
        </div>

        @if ($vehicles->isEmpty())
            <div class="bg-white border border-line rounded-2xl p-12 text-center text-muted">
                {{ __('body.no_vehicles_found') }}
            </div>
        @else
            <div class="overflow-hidden" data-carousel-wrap>
                <div class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-3 scrollbar-hide" data-carousel-track>
                    @foreach ($vehicles as $vehicle)
                        <div class="snap-start shrink-0 w-[82%] sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.75rem)] xl:w-[calc(25%-0.75rem)]" data-carousel-item>
                            <x-public.vehicle-card :vehicle="$vehicle" />
                        </div>
                    @endforeach
                </div>
            </div>

            <p data-filter-empty class="hidden bg-white border border-line rounded-2xl p-10 text-center text-muted">
                {{ __('body.no_vehicles_found') }}
            </p>
        @endif

        <div class="mt-8 flex justify-center">
            <a href="{{ route('vehicles.index') }}"
               class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-7 py-3 shadow-sm shadow-brand/25 transition-colors">
                {{ __('body.see_all_cars') }}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-vehicle-carousel]').forEach(function (root) {
                    var wrap = root.querySelector('[data-carousel-wrap]');
                    var empty = root.querySelector('[data-filter-empty]');
                    var matchLabel = root.querySelector('[data-match-label]');
                    var allBtn = root.querySelector('[data-filter-all]');
                    var typeFilter = '';
                    var categoryFilter = '';
                    var matchTemplate = @json(__('body.matching_cars_swipe', ['count' => ':count']));

                    function closePanels(except) {
                        root.querySelectorAll('[data-filter-panel]').forEach(function (panel) {
                            if (panel !== except) panel.classList.add('hidden');
                        });
                    }

                    root.querySelectorAll('[data-filter-toggle]').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            var key = btn.getAttribute('data-filter-toggle');
                            var panel = root.querySelector('[data-filter-panel="' + key + '"]');
                            if (!panel) return;
                            var wasHidden = panel.classList.contains('hidden');
                            closePanels();
                            if (wasHidden) panel.classList.remove('hidden');
                        });
                    });

                    document.addEventListener('click', function () { closePanels(); });

                    function markOptions(attr, value) {
                        root.querySelectorAll('[' + attr + ']').forEach(function (btn) {
                            btn.classList.toggle('is-active', String(btn.getAttribute(attr) || '') === String(value));
                        });
                    }

                    function applyFilters() {
                        var visible = 0;
                        root.querySelectorAll('[data-carousel-item]').forEach(function (item) {
                            var card = item.querySelector('.vehicle-card');
                            if (!card) return;
                            var typeOk = !typeFilter || card.getAttribute('data-vehicle-type') === typeFilter;
                            var catOk = !categoryFilter || String(card.getAttribute('data-vehicle-category')) === String(categoryFilter);
                            var show = typeOk && catOk;
                            item.hidden = !show;
                            if (show) visible += 1;
                        });

                        if (empty) empty.classList.toggle('hidden', visible > 0);
                        if (wrap) wrap.classList.toggle('hidden', visible === 0);
                        if (matchLabel) matchLabel.textContent = matchTemplate.replace(':count', String(visible));
                        if (allBtn) allBtn.classList.toggle('is-active', !typeFilter && !categoryFilter);
                    }

                    root.querySelectorAll('[data-filter-type]').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            typeFilter = btn.getAttribute('data-filter-type') || '';
                            markOptions('data-filter-type', typeFilter);
                            closePanels();
                            applyFilters();
                        });
                    });

                    root.querySelectorAll('[data-filter-category]').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            categoryFilter = btn.getAttribute('data-filter-category') || '';
                            markOptions('data-filter-category', categoryFilter);
                            closePanels();
                            applyFilters();
                        });
                    });

                    if (allBtn) {
                        allBtn.addEventListener('click', function () {
                            typeFilter = '';
                            categoryFilter = '';
                            markOptions('data-filter-type', '');
                            markOptions('data-filter-category', '');
                            closePanels();
                            applyFilters();
                        });
                    }
                });
            })();
        </script>
    @endpush
@endonce
