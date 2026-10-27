@php
    $steps = [
        ['id' => 'dates', 'label' => __('body.wizard_step_dates')],
        ['id' => 'locations', 'label' => __('body.wizard_step_locations')],
        ['id' => 'driver', 'label' => __('body.wizard_step_driver')],
        ['id' => 'extras', 'label' => __('body.extras')],
        ['id' => 'review', 'label' => __('body.wizard_step_summary')],
    ];
    $cancelHref = route('vehicles.show', $vehicle);
    $currentRentalType = old('rental_type', $rentalType);
    $isChauffeur = $currentRentalType === \App\Enums\RentalType::Chauffeur->value;
    $currentPickupMethod = old('pickup_method', $pickupMethod);
    $isDelivery = $currentPickupMethod === \App\Enums\PickupMethod::Delivery->value;
    $currentReturnMethod = old('return_method', $returnMethod ?? \App\Enums\ReturnMethod::Station->value);
    $isCollection = $currentReturnMethod === \App\Enums\ReturnMethod::Collection->value;
@endphp

<x-layouts.public title="Booking Details">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
        <x-common.page-header :title="__('body.booking_wizard_details_title')" :description="__('body.booking_wizard_details_description')" />

        <x-common.wizard :action="route('booking.details.store')" :steps="$steps" :start-step="$wizardStartStep ?? 1">
            <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">

            {{-- Step 1: Dates --}}
            <x-common.wizard.step :step="1" :title="__('body.wizard_step_dates_title')" :active="true">
                <div class="border border-line rounded-xl p-4 bg-surface">
                    <p class="font-semibold text-ink">{{ $vehicle->name() }}</p>
                    <p class="text-sm text-muted mt-0.5">{{ $vehicle->category?->name }} &bull; {{ number_format($vehicle->dailyPrice() ?? 0, 0) }} RWF/day</p>
                </div>

                <x-common.timezone-note />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.datetime-split
                        name="pickup_at"
                        :label="__('body.pickup_datetime')"
                        :date-placeholder="__('body.hero_pick_up_date')"
                        :value="old('pickup_at', $pickupAt)"
                        required
                        :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                        :hint="__('body.pickup_min_1_hour_hint')"
                        pair="start"
                        pair-group="booking-details"
                    />
                    <x-common.datetime-split
                        name="return_at"
                        :label="__('body.return_datetime')"
                        :date-placeholder="__('body.hero_return_date')"
                        :value="old('return_at', $returnAt)"
                        required
                        :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                        pair="end"
                        pair-group="booking-details"
                    />
                </div>

                <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
            </x-common.wizard.step>

            {{-- Step 2: Pickup & return locations --}}
            <x-common.wizard.step :step="2" :title="__('body.wizard_step_locations_title')">
                <div class="flex min-w-0 flex-col gap-2 w-full">
                    <p class="text-sm font-semibold text-ink">{{ __('body.pickup_method') }}</p>
                    <p class="text-xs text-muted">{{ __('body.pickup_method_hint') }}</p>
                    <div class="grid grid-cols-2 gap-3 max-w-md">
                        <x-common.choice name="pickup_method" value="station" :label="__('body.pickup_method_station')" id="pickup-method-station" :checked="$currentPickupMethod === 'station'" />
                        <x-common.choice name="pickup_method" value="delivery" :label="__('body.pickup_method_delivery')" id="pickup-method-delivery" :checked="$currentPickupMethod === 'delivery'" />
                    </div>
                    @error('pickup_method')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div id="station-pickup-fields" @if ($isDelivery) hidden @endif>
                    <x-common.select
                        name="pickup_station_id"
                        :label="__('body.pickup_location')"
                        :value="old('pickup_station_id', $defaultPickupStation)"
                        :options="$stations"
                        :hide-placeholder="true"
                    />
                </div>

                <div id="delivery-fields" class="space-y-4" @unless ($isDelivery) hidden @endunless>
                    <x-common.textarea
                        name="delivery_address"
                        :label="__('body.delivery_address')"
                        :value="old('delivery_address', $deliveryAddress ?? '')"
                        :rows="2"
                        :hint="__('body.delivery_address_hint')"
                    />
                    <div class="rounded-xl border border-brand/20 bg-brand-light/40 px-4 py-3">
                        <p class="text-sm font-semibold text-ink">{{ __('body.delivery_fee') }}</p>
                        <p class="text-sm text-muted mt-1">{{ number_format((float) $deliveryFee, 0) }} RWF — {{ __('body.delivery_fee_hint') }}</p>
                    </div>
                </div>

                <div class="border-t border-line pt-5 flex min-w-0 flex-col gap-2 w-full">
                    <p class="text-sm font-semibold text-ink">{{ __('body.return_method') }}</p>
                    <p class="text-xs text-muted">{{ __('body.return_method_hint') }}</p>
                    <div class="grid grid-cols-2 gap-3 max-w-md">
                        <x-common.choice name="return_method" value="station" :label="__('body.return_method_station')" id="return-method-station" :checked="$currentReturnMethod === 'station'" />
                        <x-common.choice name="return_method" value="collection" :label="__('body.return_method_collection')" id="return-method-collection" :checked="$currentReturnMethod === 'collection'" />
                    </div>
                    @error('return_method')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div id="station-return-fields" @if ($isCollection) hidden @endif>
                    <x-common.select
                        name="return_station_id"
                        :label="__('body.return_station')"
                        :value="old('return_station_id', $defaultReturnStation)"
                        :options="$stations"
                        :hide-placeholder="true"
                    />
                </div>

                <div id="collection-fields" class="space-y-4" @unless ($isCollection) hidden @endunless>
                    <x-common.textarea
                        name="collection_address"
                        :label="__('body.collection_address')"
                        :value="old('collection_address', $collectionAddress ?? '')"
                        :rows="2"
                        :hint="__('body.collection_address_hint')"
                    />
                    <div class="rounded-xl border border-brand/20 bg-brand-light/40 px-4 py-3">
                        <p class="text-sm font-semibold text-ink">{{ __('body.collection_fee') }}</p>
                        <p class="text-sm text-muted mt-1">{{ number_format((float) ($collectionFee ?? 0), 0) }} RWF — {{ __('body.collection_fee_hint') }}</p>
                    </div>
                </div>

                <x-common.wizard.nav :cancel-href="$cancelHref" />
            </x-common.wizard.step>

            {{-- Step 3: Driver / rental type --}}
            <x-common.wizard.step :step="3" :title="__('body.wizard_step_driver_title')">
                <div class="flex min-w-0 flex-col gap-2 w-full">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <p class="text-sm font-semibold text-ink">{{ __('body.rental_type') }}</p>
                        <p class="text-xs text-muted">{{ __('body.booking_carried_from_vehicle') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 max-w-md">
                        <x-common.choice name="rental_type" value="self_drive" :label="__('body.self_drive')" id="rental-type-self-drive" :checked="$currentRentalType === 'self_drive'" />
                        <x-common.choice name="rental_type" value="chauffeur" :label="__('body.chauffeur')" id="rental-type-chauffeur" :checked="$currentRentalType === 'chauffeur'" />
                    </div>
                    <x-public.chauffeur-fee :daily-fee="$chauffeurDailyFee ?? 0" :hidden="! $isChauffeur" class="mt-1" />
                    @error('rental_type')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div id="self-drive-fields" @if ($isChauffeur) hidden @endif>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-common.input
                            name="driver_license_number"
                            :label="__('body.license_number')"
                            :value="old('driver_license_number', $driverLicenseNumber ?? '')"
                            :required="! $isChauffeur"
                        />
                        <x-common.select
                            name="driver_license_category"
                            :label="__('body.driver_license_category')"
                            :value="old('driver_license_category', $driverLicenseCategory ?? '')"
                            :options="\App\Enums\LicenceCategory::cases()"
                            :placeholder="'—'"
                            :required="! $isChauffeur"
                        />
                    </div>
                </div>

                <p id="chauffeur-note" class="text-sm text-muted" @unless ($isChauffeur) hidden @endunless>
                    {{ __('body.driver_assigned_after_confirm') }}
                </p>

                <x-common.wizard.nav :cancel-href="$cancelHref" />
            </x-common.wizard.step>

            {{-- Step 4: Extras --}}
            <x-common.wizard.step :step="4" :title="__('body.extras')">
                @if ($extras->isNotEmpty())
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <p class="text-sm text-muted">{{ __('body.extras_select_hint') }}</p>
                        <p class="text-xs text-muted">{{ __('body.booking_carried_from_vehicle') }}</p>
                    </div>
                    <div class="space-y-2" data-extras-picker>
                        @foreach ($extras as $extra)
                            @php
                                $qty = (int) old('extras.'.$extra->id, $selectedExtras[$extra->id] ?? 0);
                                $checked = $qty > 0;
                            @endphp
                            <label class="extra-option group flex items-start gap-3 rounded-xl border px-3.5 py-3 cursor-pointer transition-all duration-150 {{ $checked ? 'border-brand bg-brand-light/60 shadow-sm' : 'border-line bg-white hover:border-brand/40 hover:bg-surface' }}">
                                <input
                                    type="checkbox"
                                    name="extras[{{ $extra->id }}]"
                                    value="1"
                                    data-extra-check
                                    data-price="{{ $extra->price }}"
                                    data-extra-name="{{ $extra->name }}"
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
                @else
                    <p class="text-sm text-muted">{{ __('body.no_extras_selected') }}</p>
                @endif

                <input type="hidden" name="payment_option" value="{{ \App\Enums\PaymentOption::PayNow->value }}" data-review-label="{{ __('body.pay_now') }}">
                <div class="rounded-xl border border-brand/20 bg-brand-light/40 px-4 py-3">
                    <p class="text-sm font-semibold text-ink">{{ __('body.payment_option') }}: {{ __('body.pay_now') }}</p>
                    <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('body.public_pay_now_required') }}</p>
                </div>

                <x-common.wizard.nav :cancel-href="$cancelHref" />
            </x-common.wizard.step>

            {{-- Step 5: Review --}}
            {{-- Step 5: Review + payment summary --}}
            <x-common.wizard.step :step="5" :title="__('body.wizard_step_summary_title')">
                <x-public.booking-payment-summary
                    live
                    :vehicle-name="$vehicle->name()"
                    :vehicle-daily="(float) ($vehicle->dailyPrice() ?? 0)"
                    class="border-brand/30 bg-brand-light/30"
                    data-vehicle-daily="{{ (float) ($vehicle->dailyPrice() ?? 0) }}"
                    data-chauffeur-daily="{{ (float) ($chauffeurDailyFee ?? 0) }}"
                    data-delivery-fee="{{ (float) $deliveryFee }}"
                    data-collection-fee="{{ (float) ($collectionFee ?? 0) }}"
                    data-chauffeur-value="{{ \App\Enums\RentalType::Chauffeur->value }}"
                    data-delivery-value="{{ \App\Enums\PickupMethod::Delivery->value }}"
                    data-collection-value="{{ \App\Enums\ReturnMethod::Collection->value }}"
                    data-vehicle-label="{{ $vehicle->name() }}"
                    data-day-label="{{ __('body.day') }}"
                    data-days-label="{{ __('body.days') }}"
                    data-delivery-label="{{ __('body.delivery_fee') }}"
                    data-collection-label="{{ __('body.collection_fee') }}"
                    data-chauffeur-label="{{ __('body.chauffeur') }}"
                />

                <div class="pt-2">
                    <p class="text-sm font-semibold text-ink mb-1">{{ __('body.trip_details') }}</p>
                    <p class="text-sm text-muted mb-3">{{ __('body.confirm_details') }}</p>
                    <dl>
                        <x-common.wizard.review-row :label="__('body.pickup_datetime')" for="pickup_at" />
                        <x-common.wizard.review-row :label="__('body.return_datetime')" for="return_at" />
                        <x-common.wizard.review-row :label="__('body.pickup_method')" for="pickup_method" />
                        <x-common.wizard.review-row :label="__('body.pickup_location')" for="pickup_station_id" />
                        <x-common.wizard.review-row :label="__('body.delivery_address')" for="delivery_address" />
                        <x-common.wizard.review-row :label="__('body.return_method')" for="return_method" />
                        <x-common.wizard.review-row :label="__('body.return_station')" for="return_station_id" />
                        <x-common.wizard.review-row :label="__('body.collection_address')" for="collection_address" />
                        <x-common.wizard.review-row :label="__('body.rental_type')" for="rental_type" />
                        <x-common.wizard.review-row :label="__('body.license_number')" for="driver_license_number" />
                        <x-common.wizard.review-row :label="__('body.driver_license_category')" for="driver_license_category" />
                        <x-common.wizard.review-row :label="__('body.payment_option')" for="payment_option" />
                    </dl>
                </div>

                <x-common.wizard.nav
                    :show-next="false"
                    :show-submit="true"
                    :submit-label="__('body.continue_to_payment')"
                    :cancel-href="$cancelHref"
                />
            </x-common.wizard.step>
        </x-common.wizard>
    </div>

    <script>
        (function () {
            var selfDriveRadio = document.getElementById('rental-type-self-drive');
            var chauffeurRadio = document.getElementById('rental-type-chauffeur');
            var selfDriveFields = document.getElementById('self-drive-fields');
            var chauffeurNote = document.getElementById('chauffeur-note');
            var stationMethod = document.getElementById('pickup-method-station');
            var deliveryMethod = document.getElementById('pickup-method-delivery');
            var stationFields = document.getElementById('station-pickup-fields');
            var deliveryFields = document.getElementById('delivery-fields');
            var pickupStation = document.getElementById('field-pickup-station-id');
            var deliveryAddress = document.getElementById('field-delivery-address');
            var returnStationMethod = document.getElementById('return-method-station');
            var collectionMethod = document.getElementById('return-method-collection');
            var stationReturnFields = document.getElementById('station-return-fields');
            var collectionFields = document.getElementById('collection-fields');
            var returnStation = document.getElementById('field-return-station-id');
            var collectionAddress = document.getElementById('field-collection-address');
            var form = document.querySelector('[data-form-wizard]');
            var summary = document.querySelector('[data-booking-payment-summary]');
            var pickupInput = document.getElementById('field-pickup-at');
            var returnInput = document.getElementById('field-return-at');

            function syncRentalType() {
                var isChauffeur = chauffeurRadio && chauffeurRadio.checked;
                if (selfDriveFields) selfDriveFields.hidden = isChauffeur;
                if (chauffeurNote) chauffeurNote.hidden = !isChauffeur;
                var fee = document.querySelector('[data-chauffeur-fee]');
                if (fee) fee.hidden = !isChauffeur;

                var licenseNumber = document.getElementById('field-driver-license-number');
                var licenseCategory = document.getElementById('field-driver-license-category');
                if (licenseNumber) {
                    licenseNumber.disabled = isChauffeur;
                    licenseNumber.required = !isChauffeur;
                }
                if (licenseCategory) {
                    licenseCategory.disabled = isChauffeur;
                    licenseCategory.required = !isChauffeur;
                }
                syncPaymentSummary();
            }

            function syncPickupMethod() {
                var isDelivery = deliveryMethod && deliveryMethod.checked;
                if (stationFields) stationFields.hidden = isDelivery;
                if (deliveryFields) deliveryFields.hidden = !isDelivery;
                if (pickupStation) {
                    pickupStation.disabled = isDelivery;
                    pickupStation.required = !isDelivery;
                }
                if (deliveryAddress) {
                    deliveryAddress.disabled = !isDelivery;
                    deliveryAddress.required = isDelivery;
                }
                syncPaymentSummary();
            }

            function syncReturnMethod() {
                var isCollection = collectionMethod && collectionMethod.checked;
                if (stationReturnFields) stationReturnFields.hidden = isCollection;
                if (collectionFields) collectionFields.hidden = !isCollection;
                if (returnStation) {
                    returnStation.disabled = isCollection;
                    returnStation.required = !isCollection;
                }
                if (collectionAddress) {
                    collectionAddress.disabled = !isCollection;
                    collectionAddress.required = isCollection;
                }
                syncPaymentSummary();
            }

            function formatAmount(value) {
                return Math.round(value).toLocaleString('en-US');
            }

            function rentalDays() {
                if (!pickupInput || !returnInput || !pickupInput.value || !returnInput.value) {
                    return 0;
                }
                var start = new Date(pickupInput.value);
                var end = new Date(returnInput.value);
                if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) {
                    return 0;
                }
                return Math.max(1, Math.ceil((end.getTime() - start.getTime()) / 86400000));
            }

            function syncPaymentSummary() {
                if (!summary || !form) return;

                var lines = summary.querySelector('[data-summary-lines]');
                var totalEl = summary.querySelector('[data-summary-total]');
                var daysEl = summary.querySelector('[data-summary-days]');
                if (!lines || !totalEl) return;

                var vehicleDaily = parseFloat(summary.getAttribute('data-vehicle-daily') || '0');
                var chauffeurDaily = parseFloat(summary.getAttribute('data-chauffeur-daily') || '0');
                var deliveryFee = parseFloat(summary.getAttribute('data-delivery-fee') || '0');
                var collectionFee = parseFloat(summary.getAttribute('data-collection-fee') || '0');
                var chauffeurValue = summary.getAttribute('data-chauffeur-value');
                var deliveryValue = summary.getAttribute('data-delivery-value');
                var collectionValue = summary.getAttribute('data-collection-value');
                var vehicleLabel = summary.getAttribute('data-vehicle-label') || '';
                var dayLabel = summary.getAttribute('data-day-label') || 'day';
                var daysLabel = summary.getAttribute('data-days-label') || 'days';
                var deliveryLabel = summary.getAttribute('data-delivery-label') || 'Delivery';
                var collectionLabel = summary.getAttribute('data-collection-label') || 'Collection';
                var chauffeurLabel = summary.getAttribute('data-chauffeur-label') || 'Chauffeur';

                var days = rentalDays();
                var isChauffeur = chauffeurRadio && chauffeurRadio.checked
                    || (form.querySelector('[name="rental_type"]:checked') || {}).value === chauffeurValue;
                var isDelivery = deliveryMethod && deliveryMethod.checked
                    || (form.querySelector('[name="pickup_method"]:checked') || {}).value === deliveryValue;
                var isCollection = collectionMethod && collectionMethod.checked
                    || (form.querySelector('[name="return_method"]:checked') || {}).value === collectionValue;

                var html = '';
                var total = 0;

                if (days > 0) {
                    var rental = vehicleDaily * days;
                    total += rental;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink min-w-0">' + vehicleLabel + ' <span class="text-muted">× ' + days + ' ' + (days === 1 ? dayLabel : daysLabel) + '</span></span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(rental) + ' RWF</span></div>';
                }

                if (isDelivery && deliveryFee > 0) {
                    total += deliveryFee;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + deliveryLabel + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(deliveryFee) + ' RWF</span></div>';
                }

                if (isCollection && collectionFee > 0) {
                    total += collectionFee;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + collectionLabel + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(collectionFee) + ' RWF</span></div>';
                }

                if (isChauffeur && days > 0 && chauffeurDaily > 0) {
                    var chauffeurTotal = chauffeurDaily * days;
                    total += chauffeurTotal;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + chauffeurLabel + ' <span class="text-muted">× ' + days + ' ' + (days === 1 ? dayLabel : daysLabel) + '</span></span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(chauffeurTotal) + ' RWF</span></div>';
                }

                form.querySelectorAll('[data-extra-check]').forEach(function (input) {
                    if (!input.checked) return;
                    var price = parseFloat(input.getAttribute('data-price') || '0');
                    var name = input.getAttribute('data-extra-name') || 'Extra';
                    total += price;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + name + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(price) + ' RWF</span></div>';
                });

                if (!html) {
                    html = '<p class="text-sm text-muted">' + @json(__('body.payment_summary_hint')) + '</p>';
                }

                lines.innerHTML = html;
                totalEl.textContent = formatAmount(total);
                if (daysEl) {
                    daysEl.hidden = days < 1;
                    daysEl.textContent = days > 0 ? (days + ' ' + (days === 1 ? dayLabel : daysLabel)) : '';
                }
            }

            selfDriveRadio?.addEventListener('change', syncRentalType);
            chauffeurRadio?.addEventListener('change', syncRentalType);
            stationMethod?.addEventListener('change', syncPickupMethod);
            deliveryMethod?.addEventListener('change', syncPickupMethod);
            returnStationMethod?.addEventListener('change', syncReturnMethod);
            collectionMethod?.addEventListener('change', syncReturnMethod);
            syncRentalType();
            syncPickupMethod();
            syncReturnMethod();

            document.querySelectorAll('[data-extra-check]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var card = input.closest('.extra-option');
                    if (!card) return;
                    if (input.checked) {
                        card.classList.add('border-brand', 'bg-brand-light/60', 'shadow-sm');
                        card.classList.remove('border-line', 'bg-white');
                    } else {
                        card.classList.remove('border-brand', 'bg-brand-light/60', 'shadow-sm');
                        card.classList.add('border-line', 'bg-white');
                    }
                    syncPaymentSummary();
                });
            });

            [pickupInput, returnInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', syncPaymentSummary);
                el.addEventListener('input', syncPaymentSummary);
            });

            syncPaymentSummary();

            form?.addEventListener('wizard:step', function (event) {
                if (event.detail && event.detail.isLast) {
                    syncPaymentSummary();
                }
            });
        })();
    </script>
</x-layouts.public>
