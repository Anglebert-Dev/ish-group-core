<x-layouts.public :title="$vehicle->name()">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <nav class="text-sm text-muted mb-6 flex flex-wrap items-center gap-2">
            <a href="{{ route('home') }}" class="hover:text-brand">{{ __('body.home') }}</a> /
            <a href="{{ route('vehicles.index') }}" class="hover:text-brand">{{ __('body.vehicles') }}</a> /
            <span class="text-body font-medium">{{ $vehicle->name() }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-10">

            <div class="lg:col-span-3" data-vehicle-gallery>
                @php
                    $galleryUrls = $vehicle->images->map(fn ($image) => $image->url())->values()->all();
                    if ($galleryUrls === [] && $vehicle->coverImageUrl()) {
                        $galleryUrls = [$vehicle->coverImageUrl()];
                    }
                @endphp

                <div class="relative rounded-2xl overflow-hidden bg-surface border border-line h-56 sm:h-80 lg:h-[420px] flex items-center justify-center group">
                    @if ($galleryUrls !== [])
                        <img
                            src="{{ $galleryUrls[0] }}"
                            class="w-full h-full object-cover transition-opacity duration-200"
                            alt="{{ $vehicle->name() }}"
                            data-gallery-main
                        >
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"/></svg>
                    @endif

                    @if (count($galleryUrls) > 1)
                        <button
                            type="button"
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-white/90 border border-line text-ink shadow-sm hover:bg-white hover:border-brand hover:text-brand transition-colors flex items-center justify-center"
                            data-gallery-prev
                            aria-label="{{ __('body.previous') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-white/90 border border-line text-ink shadow-sm hover:bg-white hover:border-brand hover:text-brand transition-colors flex items-center justify-center"
                            data-gallery-next
                            aria-label="{{ __('body.next') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div class="absolute bottom-3 inset-x-0 flex justify-center pointer-events-none">
                            <span class="rounded-full bg-ink/70 text-white text-xs font-semibold px-2.5 py-1 tabular-nums" data-gallery-counter>1 / {{ count($galleryUrls) }}</span>
                        </div>
                    @endif
                </div>

                @if (count($galleryUrls) > 1)
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-3 mt-3">
                        @foreach ($galleryUrls as $index => $url)
                            <button
                                type="button"
                                class="rounded-lg overflow-hidden bg-surface border h-20 focus:outline-none focus:ring-2 focus:ring-brand/30 transition-all {{ $index === 0 ? 'border-brand ring-2 ring-brand/20' : 'border-line hover:border-brand/40' }}"
                                data-gallery-thumb
                                data-index="{{ $index }}"
                                aria-label="{{ __('body.photos') }} {{ $index + 1 }}"
                            >
                                <img src="{{ $url }}" class="w-full h-full object-cover" alt="{{ $vehicle->name() }}">
                            </button>
                        @endforeach
                    </div>
                @endif

                <script type="application/json" data-gallery-sources>@json($galleryUrls)</script>

                <div class="mt-8">
                    <p class="font-semibold text-ink mb-3">{{ __('body.vehicle_information') }}</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        @foreach ([
                            ['label' => __('body.category'), 'value' => $vehicle->category?->name ?? '—'],
                            ['label' => __('body.transmission'), 'value' => $vehicle->transmission->label()],
                            ['label' => __('body.seats'), 'value' => $vehicle->seats],
                            ['label' => __('body.fuel_type'), 'value' => $vehicle->fuel_type->label()],
                        ] as $spec)
                            <div class="border border-line rounded-lg p-4 bg-white">
                                <p class="text-xs text-muted">{{ $spec['label'] }}</p>
                                <p class="font-semibold text-ink mt-1">{{ $spec['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8">
                    <p class="font-semibold text-ink mb-3">{{ __('body.vehicle_description') }}</p>
                    @if (filled($vehicle->notes))
                        <div class="rounded-xl border border-line bg-white p-4 sm:p-5">
                            <p class="text-body leading-relaxed text-sm whitespace-pre-line">{{ $vehicle->notes }}</p>
                        </div>
                    @else
                        <p class="text-sm text-muted">{{ __('body.no_vehicle_description') }}</p>
                    @endif
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="border border-line rounded-2xl p-6 sticky top-24 bg-white">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h1 class="font-display font-bold text-2xl text-ink">{{ $vehicle->name() }}</h1>
                            <p class="text-muted text-sm mt-1">{{ $vehicle->category?->name ?? '—' }} &bull; {{ $vehicle->station?->name ?? '—' }}</p>
                        </div>
                        <span data-availability-badge>
                            @if ($availableForDates ?? true)
                                <x-common.badge color="success">{{ __('body.available') }}</x-common.badge>
                            @else
                                <x-common.badge color="danger">{{ __('body.unavailable') }}</x-common.badge>
                            @endif
                        </span>
                    </div>

                    <div class="mt-5 pb-5 border-b border-line">
                        <p class="text-xs text-muted">{{ __('body.price_per_day') }}</p>
                        <p class="font-display font-bold text-2xl text-ink mt-1">{{ number_format($vehicle->dailyPrice() ?? 0, 0) }} <span class="text-sm font-medium text-muted">RWF</span></p>
                    </div>

                    <form action="{{ route('booking.details') }}" method="GET" class="mt-5 space-y-4" data-booking-quote
                          data-vehicle-daily="{{ (float) ($vehicle->dailyPrice() ?? 0) }}"
                          data-chauffeur-daily="{{ (float) ($chauffeurDailyFee ?? 0) }}"
                          data-chauffeur-value="{{ \App\Enums\RentalType::Chauffeur->value }}"
                          data-blocked-ranges='@json($blockedRanges ?? [])'
                          data-available-label="{{ __('body.available') }}"
                          data-unavailable-label="{{ __('body.unavailable') }}"
                          data-unavailable-message="{{ __('body.vehicle_unavailable_for_dates') }}">
                        <input type="hidden" name="vehicle" value="{{ $vehicle->uuid }}">
                        <x-common.timezone-note />
                        <p class="text-sm text-danger hidden" data-availability-error role="alert"></p>
                        <x-common.datetime-split
                            name="pickup_at"
                            :label="__('body.pickup_datetime')"
                            :date-placeholder="__('body.hero_pick_up_date')"
                            required
                            :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                            :hint="__('body.pickup_min_1_hour_hint')"
                            pair="start"
                            pair-group="vehicle-quote"
                        />
                        <x-common.datetime-split
                            name="return_at"
                            :label="__('body.return_datetime')"
                            :date-placeholder="__('body.hero_return_date')"
                            required
                            :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                            pair="end"
                            pair-group="vehicle-quote"
                        />
                        <div class="rounded-xl border border-line bg-surface px-4 py-3 flex items-center justify-between gap-3" data-rental-days-box hidden>
                            <p class="text-sm text-muted">{{ __('body.booking_days_label') }}</p>
                            <p class="text-sm font-semibold text-ink tabular-nums" data-rental-duration>—</p>
                        </div>
                        <x-common.select name="rental_type" :label="__('body.rental_type')" :value="old('rental_type', request('rental_type', \App\Enums\RentalType::SelfDrive->value))" :hide-placeholder="true">
                            <option value="{{ \App\Enums\RentalType::SelfDrive->value }}" @selected(old('rental_type', request('rental_type', \App\Enums\RentalType::SelfDrive->value)) === \App\Enums\RentalType::SelfDrive->value)>{{ __('body.self_drive') }}</option>
                            <option value="{{ \App\Enums\RentalType::Chauffeur->value }}" @selected(old('rental_type', request('rental_type')) === \App\Enums\RentalType::Chauffeur->value)>
                                {{ __('body.chauffeur') }}
                                @if ((float) ($chauffeurDailyFee ?? 0) > 0)
                                    (+{{ number_format($chauffeurDailyFee, 0) }} RWF/day)
                                @endif
                            </option>
                        </x-common.select>
                        <x-public.chauffeur-fee :daily-fee="$chauffeurDailyFee ?? 0" :hidden="old('rental_type', request('rental_type')) !== \App\Enums\RentalType::Chauffeur->value" />

                        @if (($optionalExtras ?? collect())->isNotEmpty())
                            @php
                                $selectedExtras = collect((array) request('extras', []))->map(fn ($id) => (string) $id);
                            @endphp
                            <div class="flex min-w-0 flex-col gap-2.5 w-full" data-extras-picker>
                                <div class="flex items-end justify-between gap-3">
                                    <label class="text-sm font-semibold text-ink">{{ __('body.extras') }}</label>
                                    <p class="text-xs text-muted">{{ __('body.extras_select_hint') }}</p>
                                </div>
                                <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                    @foreach ($optionalExtras as $extra)
                                        @php $checked = $selectedExtras->contains((string) $extra->id); @endphp
                                        <label class="extra-option group flex items-start gap-3 rounded-xl border px-3.5 py-3 cursor-pointer transition-all duration-150 {{ $checked ? 'border-brand bg-brand-light/60 shadow-sm' : 'border-line bg-white hover:border-brand/40 hover:bg-surface' }}">
                                            <input
                                                type="checkbox"
                                                name="extras[]"
                                                value="{{ $extra->id }}"
                                                data-price="{{ $extra->price }}"
                                                data-extra-check
                                                class="mt-0.5 h-4 w-4 rounded border-line text-brand focus:ring-brand/30"
                                                @checked($checked)
                                            >
                                            <span class="flex-1 min-w-0">
                                                <span class="flex items-start justify-between gap-3">
                                                    <span class="text-sm font-semibold text-ink leading-snug">{{ $extra->name }}</span>
                                                    <span class="shrink-0 text-sm font-bold text-brand tabular-nums">
                                                        {{ number_format($extra->price, 0) }}
                                                        <span class="text-[11px] font-semibold text-brand/70">RWF</span>
                                                    </span>
                                                </span>
                                                @if (filled($extra->description))
                                                    <span class="block text-xs text-muted mt-1 leading-relaxed line-clamp-2">{{ $extra->description }}</span>
                                                @else
                                                    <span class="block text-[11px] text-muted mt-1">{{ __('body.per_rental') }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="pt-4 border-t border-line">
                            <p class="text-xs text-muted">{{ __('body.total_to_pay') }}</p>
                            <p class="font-display font-bold text-2xl text-ink mt-1">
                                <span data-booking-total-amount>{{ number_format($vehicle->dailyPrice() ?? 0, 0) }}</span>
                                <span class="text-sm font-medium text-muted">RWF</span>
                            </p>
                            <p class="text-xs text-muted mt-1" data-booking-total-note>{{ __('body.price_per_day') }}</p>
                        </div>

                        <x-common.button type="submit" class="w-full" data-continue-booking>{{ __('body.continue_booking') }}</x-common.button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.querySelector('[data-booking-quote]');
            if (!form) return;

            var rentalSelect = document.getElementById('field-rental-type');
            var extrasChecks = form.querySelectorAll('[data-extra-check]');
            var pickupInput = document.getElementById('field-pickup-at');
            var returnInput = document.getElementById('field-return-at');
            var fee = form.querySelector('[data-chauffeur-fee]');
            var totalEl = form.querySelector('[data-booking-total-amount]');
            var noteEl = form.querySelector('[data-booking-total-note]');
            var durationEl = form.querySelector('[data-rental-duration]');
            var durationBox = form.querySelector('[data-rental-days-box]');
            var availabilityError = form.querySelector('[data-availability-error]');
            var availabilityBadge = document.querySelector('[data-availability-badge]');
            var continueBtn = form.querySelector('[data-continue-booking]');
            var chauffeurValue = form.getAttribute('data-chauffeur-value');
            var vehicleDaily = parseFloat(form.getAttribute('data-vehicle-daily') || '0');
            var chauffeurDaily = parseFloat(form.getAttribute('data-chauffeur-daily') || '0');
            var dayLabel = @json(__('body.day'));
            var daysLabel = @json(__('body.days'));
            var hourLabel = @json(__('body.hour'));
            var hoursLabel = @json(__('body.hours'));
            var pricePerDayLabel = @json(__('body.price_per_day'));
            var availableLabel = form.getAttribute('data-available-label') || 'Available';
            var unavailableLabel = form.getAttribute('data-unavailable-label') || 'Unavailable';
            var unavailableMessage = form.getAttribute('data-unavailable-message') || '';
            var blockedRanges = [];

            try {
                blockedRanges = JSON.parse(form.getAttribute('data-blocked-ranges') || '[]');
            } catch (e) {
                blockedRanges = [];
            }

            function hasCompleteDates() {
                if (!pickupInput || !returnInput || !pickupInput.value || !returnInput.value) {
                    return false;
                }

                var start = new Date(pickupInput.value);
                var end = new Date(returnInput.value);
                return !isNaN(start.getTime()) && !isNaN(end.getTime()) && end > start;
            }

            function overlapsBlocked() {
                if (!hasCompleteDates() || !Array.isArray(blockedRanges) || blockedRanges.length === 0) {
                    return false;
                }

                var start = new Date(pickupInput.value).getTime();
                var end = new Date(returnInput.value).getTime();

                return blockedRanges.some(function (range) {
                    var blockedStart = new Date(range.start).getTime();
                    var blockedEnd = new Date(range.end).getTime();
                    if (isNaN(blockedStart) || isNaN(blockedEnd)) return false;
                    return start < blockedEnd && end > blockedStart;
                });
            }

            function setAvailabilityUi(isAvailable) {
                if (availabilityError) {
                    if (isAvailable) {
                        availabilityError.textContent = '';
                        availabilityError.classList.add('hidden');
                    } else {
                        availabilityError.textContent = unavailableMessage;
                        availabilityError.classList.remove('hidden');
                    }
                }

                if (continueBtn) {
                    continueBtn.disabled = !isAvailable;
                    continueBtn.classList.toggle('opacity-50', !isAvailable);
                    continueBtn.classList.toggle('pointer-events-none', !isAvailable);
                }

                if (availabilityBadge) {
                    var color = isAvailable ? 'success' : 'danger';
                    var label = isAvailable ? availableLabel : unavailableLabel;
                    availabilityBadge.innerHTML =
                        '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ' +
                        (isAvailable ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger') +
                        '">' + label + '</span>';
                }
            }

            function rentalDuration() {
                if (!hasCompleteDates()) {
                    return null;
                }

                var start = new Date(pickupInput.value);
                var end = new Date(returnInput.value);
                var totalMs = end.getTime() - start.getTime();
                var totalHours = Math.floor(totalMs / 3600000);
                var days = Math.floor(totalHours / 24);
                var hours = totalHours % 24;
                var billableDays = Math.max(1, Math.ceil(totalMs / 86400000));

                if (days === 0 && hours === 0) {
                    hours = 1;
                }

                return { days: days, hours: hours, billableDays: billableDays };
            }

            function formatDuration(duration) {
                var dayText = duration.days === 1 ? dayLabel : daysLabel;
                var hourText = duration.hours === 1 ? hourLabel : hoursLabel;

                if (duration.days > 0 && duration.hours > 0) {
                    return duration.days + ' ' + dayText + ' ' + duration.hours + ' ' + hourText;
                }
                if (duration.days > 0) {
                    return duration.days + ' ' + dayText;
                }
                return duration.hours + ' ' + hourText;
            }

            function extrasTotal() {
                return Array.from(extrasChecks).reduce(function (sum, input) {
                    if (!input.checked) return sum;
                    return sum + parseFloat(input.getAttribute('data-price') || '0');
                }, 0);
            }

            function syncExtraStyles() {
                extrasChecks.forEach(function (input) {
                    var card = input.closest('.extra-option');
                    if (!card) return;
                    if (input.checked) {
                        card.classList.add('border-brand', 'bg-brand-light/60', 'shadow-sm');
                        card.classList.remove('border-line', 'bg-white');
                    } else {
                        card.classList.remove('border-brand', 'bg-brand-light/60', 'shadow-sm');
                        card.classList.add('border-line', 'bg-white');
                    }
                });
            }

            function formatAmount(value) {
                return Math.round(value).toLocaleString('en-US');
            }

            function sync() {
                var isChauffeur = rentalSelect && rentalSelect.value === chauffeurValue;
                if (fee) fee.hidden = !isChauffeur;

                var duration = rentalDuration();
                var days = duration ? duration.billableDays : 0;
                var rentalTotal = days > 0 ? vehicleDaily * days : vehicleDaily;
                var chauffeurTotal = isChauffeur && days > 0 ? chauffeurDaily * days : (isChauffeur ? chauffeurDaily : 0);
                var extras = extrasTotal();
                var total = rentalTotal + chauffeurTotal + extras;
                var datesOk = !overlapsBlocked();

                if (durationBox) durationBox.hidden = !duration;
                if (durationEl) durationEl.textContent = duration ? formatDuration(duration) : '—';

                if (totalEl) totalEl.textContent = formatAmount(total);

                if (noteEl) {
                    if (!duration) {
                        noteEl.textContent = pricePerDayLabel;
                    } else {
                        var parts = [days + ' × ' + formatAmount(vehicleDaily) + ' RWF'];
                        if (chauffeurTotal > 0) {
                            parts.push('+ ' + formatAmount(chauffeurTotal) + ' RWF chauffeur');
                        }
                        if (extras > 0) {
                            parts.push('+ ' + formatAmount(extras) + ' RWF extras');
                        }
                        noteEl.textContent = parts.join(' ');
                    }
                }

                if (hasCompleteDates()) {
                    setAvailabilityUi(datesOk);
                } else {
                    setAvailabilityUi(true);
                }

                syncExtraStyles();
            }

            [rentalSelect, pickupInput, returnInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', sync);
                el.addEventListener('input', sync);
            });

            extrasChecks.forEach(function (input) {
                input.addEventListener('change', sync);
            });

            form.addEventListener('submit', function (event) {
                if (overlapsBlocked()) {
                    event.preventDefault();
                    setAvailabilityUi(false);
                }
            });

            sync();
        })();
    </script>

    <script>
        (function () {
            var root = document.querySelector('[data-vehicle-gallery]');
            if (!root) return;

            var sourcesEl = root.querySelector('[data-gallery-sources]');
            var main = root.querySelector('[data-gallery-main]');
            var counter = root.querySelector('[data-gallery-counter]');
            var thumbs = Array.from(root.querySelectorAll('[data-gallery-thumb]'));
            if (!sourcesEl || !main) return;

            var sources = [];
            try {
                sources = JSON.parse(sourcesEl.textContent || '[]');
            } catch (e) {
                return;
            }
            if (!Array.isArray(sources) || sources.length < 2) return;

            var index = 0;

            function setActive(nextIndex) {
                index = (nextIndex + sources.length) % sources.length;
                main.style.opacity = '0.55';
                window.setTimeout(function () {
                    main.src = sources[index];
                    main.style.opacity = '1';
                }, 120);

                if (counter) {
                    counter.textContent = (index + 1) + ' / ' + sources.length;
                }

                thumbs.forEach(function (thumb) {
                    var active = parseInt(thumb.getAttribute('data-index'), 10) === index;
                    thumb.classList.toggle('border-brand', active);
                    thumb.classList.toggle('ring-2', active);
                    thumb.classList.toggle('ring-brand/20', active);
                    thumb.classList.toggle('border-line', !active);
                });
            }

            root.querySelector('[data-gallery-prev]')?.addEventListener('click', function () {
                setActive(index - 1);
            });
            root.querySelector('[data-gallery-next]')?.addEventListener('click', function () {
                setActive(index + 1);
            });
            thumbs.forEach(function (thumb) {
                thumb.addEventListener('click', function () {
                    setActive(parseInt(thumb.getAttribute('data-index'), 10) || 0);
                });
            });

            root.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') setActive(index - 1);
                if (e.key === 'ArrowRight') setActive(index + 1);
            });
        })();
    </script>

</x-layouts.public>
