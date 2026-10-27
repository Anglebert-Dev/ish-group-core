@php
    $pickupValue = (string) request('pickup_date');
    $returnValue = (string) request('return_date');
    $earliestPickup = \App\Libraries\GeneralLibrary::earliestPickupLocalValue();
@endphp

<div class="max-w-6xl mx-auto">
    <form id="hero-booking-form" action="{{ route('booking.search') }}" method="GET"
          class="hero-search-card bg-white/95 backdrop-blur-md rounded-3xl border border-white/70 shadow-[0_24px_60px_rgba(2,7,15,0.35)] p-3 sm:p-4"
          novalidate>

        <input type="hidden" name="rental_type" value="{{ \App\Enums\RentalType::SelfDrive->value }}">

        <div class="flex flex-col lg:flex-row lg:items-end gap-3 lg:gap-4">
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3 lg:gap-4">
                <x-common.datetime-split
                    name="pickup_date"
                    :heading="__('body.hero_pickup_datetime')"
                    :date-placeholder="__('body.hero_pick_up_date')"
                    :value="$pickupValue"
                    :min="$earliestPickup"
                    required
                    pair="start"
                    pair-group="hero"
                />
                <x-common.datetime-split
                    name="return_date"
                    :heading="__('body.hero_return_datetime')"
                    :date-placeholder="__('body.hero_return_date')"
                    :value="$returnValue"
                    :min="$earliestPickup"
                    required
                    pair="end"
                    pair-group="hero"
                />
            </div>

            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl h-[52px] px-6 text-sm transition-colors shadow-sm shadow-brand/30 w-full lg:w-auto lg:min-w-[8rem] shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                {{ __('body.search') }}
            </button>
        </div>

        <p class="mt-2 px-1 text-[11px] text-muted" data-hero-hint>{{ __('body.pickup_min_1_hour_hint') }}</p>
        <p class="mt-2 px-1 text-[11px] text-danger hidden" data-hero-error role="alert"></p>
    </form>
</div>

<script>
    (function () {
        const form = document.getElementById('hero-booking-form');
        if (!form) return;

        const errorEl = form.querySelector('[data-hero-error]');
        const hintEl = form.querySelector('[data-hero-hint]');
        const requiredMsg = @json(__('body.hero_search_dates_required'));

        form.addEventListener('submit', function (event) {
            if (window.DateTimeSplit) {
                form.querySelectorAll('[data-datetime-split]').forEach(function (el) {
                    window.DateTimeSplit.sync(el);
                });
            }

            const pickup = form.querySelector('[name="pickup_date"]')?.value || '';
            const ret = form.querySelector('[name="return_date"]')?.value || '';
            if (pickup && ret) {
                errorEl?.classList.add('hidden');
                hintEl?.classList.remove('hidden');
                return;
            }

            event.preventDefault();
            if (errorEl) {
                errorEl.textContent = requiredMsg;
                errorEl.classList.remove('hidden');
                hintEl?.classList.add('hidden');
            }

            const missing = form.querySelector('[data-datetime-split] [data-part="date"]:not([value]), [data-datetime-split] [data-part="date"][value=""]')
                || form.querySelector('[data-datetime-split] [data-part="time"]:not([value]), [data-datetime-split] [data-part="time"][value=""]');
            missing?.closest('.dt-split-box')?.click();
        });
    })();
</script>
