@php
    $filterAction = request()->routeIs('booking.search') ? route('booking.search') : route('vehicles.index');
    $preserved = collect($filters ?? [])->only(['rental_type', 'return_station', 'round_trip'])->filter(fn ($v) => $v !== null && $v !== '');
    $activeFilterCount = collect($filters ?? [])
        ->only(['search', 'category', 'station', 'transmission', 'fuel_type', 'min_price', 'max_price', 'pickup_date', 'return_date'])
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->count();
    $filtersOpen = false;
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 {{ isset($noTopPad) ? 'pb-10' : 'py-10' }}">
    <form id="vehicle-filters" method="GET" action="{{ $filterAction }}" class="grid grid-cols-1 lg:grid-cols-4 gap-6 lg:gap-8">
        @foreach ($preserved as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        {{-- RESULTS --}}
        <div class="lg:col-span-3 order-2 lg:order-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <p class="text-sm text-muted">{{ __('body.vehicles_to_rent', ['count' => $vehicles->total()]) }}</p>
                <x-common.select
                    name="sort"
                    :value="$filters['sort'] ?? ''"
                    :placeholder="__('body.recommended')"
                    compact
                    class="w-full sm:w-auto sm:min-w-[12rem]"
                    data-filter-change
                    :searchable="false"
                >
                    <option value="">{{ __('body.recommended') }}</option>
                    <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('body.price_low_high') }}</option>
                    <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('body.price_high_low') }}</option>
                </x-common.select>
            </div>

            @if ($vehicles->isEmpty())
                <div class="bg-white border border-line rounded-xl p-12 text-center text-muted">
                    {{ __('body.no_vehicles_found') }}
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach ($vehicles as $vehicle)
                        <x-public.vehicle-card :vehicle="$vehicle" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $vehicles->links() }}
                </div>
            @endif
        </div>

        {{-- FILTERS --}}
        <aside class="lg:col-span-1 order-1">
            <div class="bg-white border border-line rounded-xl lg:sticky lg:top-24 overflow-hidden">
                <button
                    type="button"
                    class="lg:hidden w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left"
                    data-filter-toggle
                    aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}"
                    aria-controls="vehicle-filter-panel"
                >
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="font-semibold text-ink">{{ __('body.filters') }}</span>
                        @if ($activeFilterCount > 0)
                            <span class="inline-flex items-center rounded-full bg-brand-light text-brand text-[11px] font-semibold px-2 py-0.5 tabular-nums">
                                {{ __('body.filters_active_count', ['count' => $activeFilterCount]) }}
                            </span>
                        @endif
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-medium text-brand" data-filter-toggle-label>
                            {{ $filtersOpen ? __('body.hide_filters') : __('body.show_filters') }}
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted transition-transform duration-200 {{ $filtersOpen ? 'rotate-180' : '' }}" data-filter-chevron fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </button>

                <div
                    id="vehicle-filter-panel"
                    data-filter-panel
                    class="{{ $filtersOpen ? '' : 'hidden' }} lg:block border-t border-line lg:border-t-0 p-4 sm:p-5"
                >
                    <div class="hidden lg:flex items-center justify-between mb-5">
                        <p class="font-semibold text-ink">{{ __('body.filter_by') }}</p>
                        <a href="{{ $filterAction }}" class="text-xs font-medium text-brand hover:text-brand-dark">{{ __('body.reset_filters') }}</a>
                    </div>

                    <div class="lg:hidden flex items-center justify-end mb-4">
                        <a href="{{ $filterAction }}" class="text-xs font-medium text-brand hover:text-brand-dark">{{ __('body.reset_filters') }}</a>
                    </div>

                    <div class="mb-5">
                        <label for="filter-search" class="text-xs font-semibold text-muted uppercase tracking-wide mb-3 block">{{ __('body.search') }}</label>
                        <input id="filter-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('body.search_vehicles_placeholder') }}"
                               data-filter-search
                               autocomplete="off"
                               class="w-full h-10 rounded-lg border border-line px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                    </div>

                    <div class="mb-6 space-y-3">
                        <x-common.datetime-split
                            name="pickup_date"
                            compact
                            :label="__('body.pickup_date')"
                            :date-placeholder="__('body.hero_pick_up_date')"
                            :value="$filters['pickup_date'] ?? ''"
                            :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                            pair="start"
                            pair-group="vehicle-filters"
                            data-filter-change
                        />
                        <x-common.datetime-split
                            name="return_date"
                            compact
                            :label="__('body.return_date')"
                            :date-placeholder="__('body.hero_return_date')"
                            :value="$filters['return_date'] ?? ''"
                            :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                            pair="end"
                            pair-group="vehicle-filters"
                            data-filter-change
                        />
                    </div>

                    <div class="mb-6">
                        <x-common.select
                            id="filter-category"
                            name="category"
                            :label="__('body.category')"
                            :value="$filters['category'] ?? ''"
                            :placeholder="__('body.all_categories')"
                            compact
                            data-filter-change
                        >
                            <option value="">{{ __('body.all_categories') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->uuid }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->uuid)>{{ $cat->name }}</option>
                            @endforeach
                        </x-common.select>
                    </div>

                    @isset($stations)
                        <div class="mb-6">
                            <x-common.select
                                id="filter-station"
                                name="station"
                                :label="__('body.station')"
                                :value="$filters['station'] ?? ''"
                                :placeholder="__('body.any_station')"
                                compact
                                data-filter-change
                            >
                                <option value="">{{ __('body.any_station') }}</option>
                                @foreach ($stations as $station)
                                    <option value="{{ $station->uuid }}" @selected((string) ($filters['station'] ?? '') === (string) $station->uuid)>{{ $station->name }}</option>
                                @endforeach
                            </x-common.select>
                        </div>
                    @endisset

                    <div class="mb-6">
                        <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-3">{{ __('body.price_range_per_day') }}</p>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" data-filter-change value="{{ $filters['min_price'] ?? '' }}" placeholder="Min" min="0"
                                   aria-label="Min"
                                   class="w-full h-10 rounded-lg border border-line px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                            <span class="text-muted">–</span>
                            <input type="number" name="max_price" data-filter-change value="{{ $filters['max_price'] ?? '' }}" placeholder="Max" min="0"
                                   aria-label="Max"
                                   class="w-full h-10 rounded-lg border border-line px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        </div>
                    </div>

                    <div class="mb-6">
                        <x-common.select
                            id="filter-transmission"
                            name="transmission"
                            :label="__('body.transmission')"
                            :value="$filters['transmission'] ?? ''"
                            :placeholder="__('body.any_transmission')"
                            compact
                            data-filter-change
                            :searchable="false"
                        >
                            <option value="">{{ __('body.any_transmission') }}</option>
                            @foreach (\App\Enums\TransmissionType::cases() as $case)
                                <option value="{{ $case->value }}" @selected(($filters['transmission'] ?? '') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-common.select>
                    </div>

                    <div class="mb-1">
                        <x-common.select
                            id="filter-fuel-type"
                            name="fuel_type"
                            :label="__('body.fuel_type')"
                            :value="$filters['fuel_type'] ?? ''"
                            :placeholder="__('body.any_fuel_type')"
                            compact
                            data-filter-change
                            :searchable="false"
                        >
                            <option value="">{{ __('body.any_fuel_type') }}</option>
                            @foreach (\App\Enums\FuelType::cases() as $case)
                                <option value="{{ $case->value }}" @selected(($filters['fuel_type'] ?? '') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-common.select>
                    </div>
                </div>
            </div>
        </aside>
    </form>
</div>

<script>
    (function () {
        const form = document.getElementById('vehicle-filters');
        if (!form) return;

        const searchInput = form.querySelector('[data-filter-search]');
        const focusKey = 'vehicle-filter-search-focus';
        const toggle = form.querySelector('[data-filter-toggle]');
        const panel = form.querySelector('[data-filter-panel]');
        const chevron = form.querySelector('[data-filter-chevron]');
        const toggleLabel = form.querySelector('[data-filter-toggle-label]');
        const showLabel = @json(__('body.show_filters'));
        const hideLabel = @json(__('body.hide_filters'));
        let searchTimer = null;

        function setFiltersOpen(open) {
            if (!toggle || !panel) return;
            panel.classList.toggle('hidden', !open);
            panel.classList.toggle('lg:block', true);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (chevron) chevron.classList.toggle('rotate-180', open);
            if (toggleLabel) toggleLabel.textContent = open ? hideLabel : showLabel;
        }

        toggle?.addEventListener('click', function () {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            setFiltersOpen(open);
        });

        function submitFilters() {
            clearTimeout(searchTimer);
            form.requestSubmit();
        }

        if (searchInput && sessionStorage.getItem(focusKey) === '1') {
            sessionStorage.removeItem(focusKey);
            setFiltersOpen(true);
            const len = searchInput.value.length;
            searchInput.focus();
            try { searchInput.setSelectionRange(len, len); } catch (_) {}
        }

        form.addEventListener('input', function (event) {
            const field = event.target;
            if (!field || !field.hasAttribute('data-filter-search')) return;

            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                sessionStorage.setItem(focusKey, '1');
                submitFilters();
            }, 350);
        });

        form.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || !event.target || !event.target.hasAttribute('data-filter-search')) return;
            event.preventDefault();
            sessionStorage.setItem(focusKey, '1');
            submitFilters();
        });

        form.addEventListener('change', function (event) {
            const field = event.target;
            if (!field || !field.hasAttribute('data-filter-change')) return;

            if (field.name === 'pickup_date' || field.name === 'return_date') {
                const pickup = form.querySelector('[name="pickup_date"]')?.value;
                const ret = form.querySelector('[name="return_date"]')?.value;
                if ((pickup && !ret) || (!pickup && ret)) return;
            }

            clearTimeout(searchTimer);
            submitFilters();
        });

        form.addEventListener('submit', function () {
            form.querySelectorAll('input, select').forEach(function (el) {
                if (el.name && String(el.value).trim() === '') {
                    el.disabled = true;
                }
            });
        });
    })();
</script>
