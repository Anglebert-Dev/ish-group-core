@php
    use App\Enums\PickupMethod;
    use App\Enums\ReturnMethod;

    $currentPickupMethod = old('pickup_method', PickupMethod::Station->value);
    $currentReturnMethod = old('return_method', ReturnMethod::Station->value);
    $isDelivery = $currentPickupMethod === PickupMethod::Delivery->value;
    $isCollection = $currentReturnMethod === ReturnMethod::Collection->value;

    $steps = [
        ['id' => 'customer', 'label' => __('body.customer')],
        ['id' => 'dates', 'label' => __('body.wizard_step_dates')],
        ['id' => 'locations', 'label' => __('body.wizard_step_locations')],
        ['id' => 'category', 'label' => __('body.category')],
        ['id' => 'rental', 'label' => __('body.wizard_step_driver')],
        ['id' => 'extras', 'label' => __('body.extras')],
        ['id' => 'review', 'label' => __('body.wizard_step_summary')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_booking') }}">
    <x-common.page-header :title="__('body.add_booking')" />

    <x-common.wizard :action="route('bookings.store')" :steps="$steps">
        <x-common.wizard.step :step="1" :title="__('body.customer_type')" :active="true">
            <div class="grid grid-cols-2 gap-3 max-w-md">
                <x-common.choice name="customer_type" value="individual" :label="__('body.individual')" id="customer-type-individual" :checked="old('customer_type', 'individual') === 'individual'" />
                <x-common.choice name="customer_type" value="corporate" :label="__('body.corporate')" id="customer-type-corporate" :checked="old('customer_type') === 'corporate'" />
            </div>

            <div id="individual-customer-field" data-wizard-show-when="customer_type:individual">
                <x-common.select name="customer_id" :label="__('body.select_customer')" :value="old('customer_id')" placeholder="—" required>
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id') === $customer->id)>{{ $customer->name }} ({{ $customer->email }})</option>
                    @endforeach
                </x-common.select>
            </div>

            <div id="corporate-customer-field" data-wizard-show-when="customer_type:corporate" hidden>
                <x-common.select name="corporate_customer_id" :label="__('body.select_corporate_customer')" :value="old('corporate_customer_id')" placeholder="—" required>
                    <option value="">—</option>
                    @foreach ($corporateCustomers as $corporate)
                        <option value="{{ $corporate->id }}" @selected((int) old('corporate_customer_id') === $corporate->id)>{{ $corporate->company_name }}</option>
                    @endforeach
                </x-common.select>
            </div>

            <x-common.wizard.nav :show-back="false" :cancel-href="route('bookings.index')" />
        </x-common.wizard.step>

        <x-common.wizard.step :step="2" :title="__('body.wizard_step_dates_title')">
            <x-common.timezone-note />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.datetime-split
                    name="pickup_at"
                    :label="__('body.pickup_datetime')"
                    :date-placeholder="__('body.hero_pick_up_date')"
                    :value="old('pickup_at')"
                    required
                    :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                    :hint="__('body.pickup_min_1_hour_hint')"
                    pair="start"
                    pair-group="staff-booking"
                />
                <x-common.datetime-split
                    name="return_at"
                    :label="__('body.return_datetime')"
                    :date-placeholder="__('body.hero_return_date')"
                    :value="old('return_at')"
                    required
                    :min="\App\Libraries\GeneralLibrary::earliestPickupLocalValue()"
                    pair="end"
                    pair-group="staff-booking"
                />
            </div>
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="3" :title="__('body.wizard_step_locations_title')">
            <div class="flex min-w-0 flex-col gap-2 w-full">
                <p class="text-sm font-semibold text-ink">{{ __('body.pickup_method') }}</p>
                <p class="text-xs text-muted">{{ __('body.pickup_method_hint') }}</p>
                <div class="grid grid-cols-2 gap-3 max-w-md">
                    <x-common.choice name="pickup_method" value="station" :label="__('body.pickup_method_station')" id="pickup-method-station" :checked="$currentPickupMethod === 'station'" />
                    <x-common.choice name="pickup_method" value="delivery" :label="__('body.pickup_method_delivery')" id="pickup-method-delivery" :checked="$currentPickupMethod === 'delivery'" />
                </div>
                @error('pickup_method')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            </div>

            <div id="station-pickup-fields" data-wizard-show-when="pickup_method:station" @if ($isDelivery) hidden @endif>
                <x-common.select
                    name="pickup_station_id"
                    :label="__('body.pickup_location')"
                    :value="old('pickup_station_id', $defaultPickupStation)"
                    :options="$stations"
                    :hide-placeholder="true"
                    required
                />
            </div>

            <div id="delivery-fields" class="space-y-4" data-wizard-show-when="pickup_method:delivery" @unless ($isDelivery) hidden @endunless>
                <x-common.textarea
                    name="delivery_address"
                    :label="__('body.delivery_address')"
                    :value="old('delivery_address')"
                    :rows="2"
                    :hint="__('body.delivery_address_hint')"
                    required
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

            <div id="station-return-fields" data-wizard-show-when="return_method:station" @if ($isCollection) hidden @endif>
                <x-common.select
                    name="return_station_id"
                    :label="__('body.return_station')"
                    :value="old('return_station_id', $defaultReturnStation)"
                    :options="$stations"
                    :hide-placeholder="true"
                    required
                />
            </div>

            <div id="collection-fields" class="space-y-4" data-wizard-show-when="return_method:collection" @unless ($isCollection) hidden @endunless>
                <x-common.textarea
                    name="collection_address"
                    :label="__('body.collection_address')"
                    :value="old('collection_address')"
                    :rows="2"
                    :hint="__('body.collection_address_hint')"
                    required
                />
                <div class="rounded-xl border border-brand/20 bg-brand-light/40 px-4 py-3">
                    <p class="text-sm font-semibold text-ink">{{ __('body.collection_fee') }}</p>
                    <p class="text-sm text-muted mt-1">{{ number_format((float) $collectionFee, 0) }} RWF — {{ __('body.collection_fee_hint') }}</p>
                </div>
            </div>

            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="4" :title="__('body.category')">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.select name="vehicle_category_id" :label="__('body.category')" :value="old('vehicle_category_id')" required placeholder="—" :hint="__('body.booking_rate_updates_on_assign')">
                    <option value="">—</option>
                    @foreach ($categories as $cat)
                        <option
                            value="{{ $cat->id }}"
                            data-daily-price="{{ (float) $cat->quoteDailyPrice() }}"
                            data-category-name="{{ $cat->name }}"
                            @selected((int) old('vehicle_category_id') === $cat->id)
                        >{{ $cat->name }} ({{ $cat->fleetDailyPriceLabel() }})</option>
                    @endforeach
                </x-common.select>
                <x-common.select
                    name="payment_option"
                    id="payment-option"
                    :label="__('body.payment_option')"
                    :value="old('payment_option', \App\Enums\PaymentOption::PayAtPickup->value)"
                    :hide-placeholder="true"
                    required
                    :hint="__('body.payment_option_individual_default')"
                >
                    @foreach (\App\Enums\PaymentOption::cases() as $option)
                        <option
                            value="{{ $option->value }}"
                            data-customer-type="{{ $option === \App\Enums\PaymentOption::CorporateBilling ? 'corporate' : 'both' }}"
                            data-hint="{{ $option->hint() }}"
                            @selected(old('payment_option', \App\Enums\PaymentOption::PayAtPickup->value) === $option->value)
                        >{{ $option->label() }}</option>
                    @endforeach
                </x-common.select>
            </div>
            <p id="trip-availability-status" class="text-sm text-muted" hidden role="status" aria-live="polite"></p>
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="5" :title="__('body.wizard_step_driver_title')">
            <div class="grid grid-cols-2 gap-3 max-w-md">
                <x-common.choice name="rental_type" value="self_drive" :label="__('body.self_drive')" id="rental-type-self-drive" :checked="old('rental_type', 'self_drive') === 'self_drive'" />
                <x-common.choice name="rental_type" value="chauffeur" :label="__('body.chauffeur')" id="rental-type-chauffeur" :checked="old('rental_type') === 'chauffeur'" />
            </div>
            <x-public.chauffeur-fee :daily-fee="$chauffeurDailyFee ?? 0" :hidden="old('rental_type') !== 'chauffeur'" class="mt-3" />

            <div id="self-drive-fields" data-wizard-show-when="rental_type:self_drive">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.input name="driver_license_number" :label="__('body.license_number')" :value="old('driver_license_number')" required />
                    <x-common.select name="driver_license_category" :label="__('body.driver_license_category')" :value="old('driver_license_category')" :options="\App\Enums\LicenceCategory::cases()" :placeholder="'—'" required />
                </div>
            </div>

            <p id="chauffeur-note" class="text-sm text-muted" data-wizard-show-when="rental_type:chauffeur" hidden>
                {{ __('body.driver_assigned_after_confirm') }}
            </p>
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="6" :title="__('body.extras')">
            @if ($extras->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($extras as $extra)
                        <div class="flex items-center justify-between gap-3 border border-line rounded-xl px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-ink">{{ $extra->name }}</p>
                                <p class="text-xs text-muted">{{ number_format($extra->price, 0) }} RWF</p>
                            </div>
                            <input type="number" name="extras[{{ $extra->id }}]" value="{{ old('extras.'.$extra->id, 0) }}" min="0" max="20"
                                   class="w-20 h-12 rounded-xl border border-line px-2 text-sm text-center bg-white shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                   data-extra-qty
                                   data-extra-price="{{ (float) $extra->price }}"
                                   data-extra-name="{{ $extra->name }}">
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">{{ __('body.no_extras_selected') }}</p>
            @endif

            <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes')" :rows="3" />
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="7" :title="__('body.wizard_step_summary_title')">
            <x-public.booking-payment-summary
                live
                class="border-brand/30 bg-brand-light/30"
                data-staff-category-summary="1"
                data-vehicle-daily="0"
                data-chauffeur-daily="{{ (float) ($chauffeurDailyFee ?? 0) }}"
                data-delivery-fee="{{ (float) $deliveryFee }}"
                data-collection-fee="{{ (float) $collectionFee }}"
                data-chauffeur-value="{{ \App\Enums\RentalType::Chauffeur->value }}"
                data-delivery-value="{{ PickupMethod::Delivery->value }}"
                data-collection-value="{{ ReturnMethod::Collection->value }}"
                data-vehicle-label=""
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
                    <x-common.wizard.review-row :label="__('body.customer_type')" for="customer_type" />
                    <x-common.wizard.review-row :label="__('body.select_customer')" for="customer_id" />
                    <x-common.wizard.review-row :label="__('body.select_corporate_customer')" for="corporate_customer_id" />
                    <x-common.wizard.review-row :label="__('body.pickup_datetime')" for="pickup_at" />
                    <x-common.wizard.review-row :label="__('body.return_datetime')" for="return_at" />
                    <x-common.wizard.review-row :label="__('body.pickup_method')" for="pickup_method" />
                    <x-common.wizard.review-row :label="__('body.pickup_location')" for="pickup_station_id" />
                    <x-common.wizard.review-row :label="__('body.delivery_address')" for="delivery_address" />
                    <x-common.wizard.review-row :label="__('body.return_method')" for="return_method" />
                    <x-common.wizard.review-row :label="__('body.return_station')" for="return_station_id" />
                    <x-common.wizard.review-row :label="__('body.collection_address')" for="collection_address" />
                    <x-common.wizard.review-row :label="__('body.category')" for="vehicle_category_id" />
                    <x-common.wizard.review-row :label="__('body.rental_type')" for="rental_type" />
                    <x-common.wizard.review-row :label="__('body.payment_option')" for="payment_option" />
                </dl>
            </div>
            <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="route('bookings.index')" />
        </x-common.wizard.step>
    </x-common.wizard>

    <script>
        (function () {
            var form = document.querySelector('[data-form-wizard]');
            if (!form) return;

            var fallbackStationId = @json($fallbackStationId);
            var statusEl = document.getElementById('trip-availability-status');
            var checkUrl = @json(route('bookings.check-availability'));
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || form.querySelector('input[name="_token"]')?.value;
            var debounceTimer = null;
            var summary = document.querySelector('[data-staff-category-summary]');
            var pickupInput = form.querySelector('[name="pickup_at"]');
            var returnInput = form.querySelector('[name="return_at"]');

            function syncChauffeurFee() {
                var chauffeurRadio = document.getElementById('rental-type-chauffeur');
                var fee = document.querySelector('[data-chauffeur-fee]');
                if (fee && chauffeurRadio) {
                    fee.hidden = !chauffeurRadio.checked;
                }
            }

            form.addEventListener('change', function (event) {
                if (event.target && event.target.name === 'rental_type') {
                    syncChauffeurFee();
                }
                if (event.target && (event.target.name === 'vehicle_category_id' || event.target.name === 'pickup_method' || event.target.name === 'return_method' || event.target.name === 'rental_type')) {
                    syncPaymentSummary();
                }
            });
            syncChauffeurFee();

            function effectivePickupStationId() {
                var pickupMethod = (form.querySelector('[name="pickup_method"]:checked') || {}).value || 'station';
                if (pickupMethod === 'station') {
                    return form.querySelector('[name="pickup_station_id"]')?.value || '';
                }
                var returnMethod = (form.querySelector('[name="return_method"]:checked') || {}).value || 'station';
                if (returnMethod === 'station') {
                    return form.querySelector('[name="return_station_id"]')?.value || '';
                }
                return String(fallbackStationId || '');
            }

            function tripPayload() {
                var category = form.querySelector('[name="vehicle_category_id"]');
                var pickup = form.querySelector('[name="pickup_at"]');
                var ret = form.querySelector('[name="return_at"]');
                var stationId = effectivePickupStationId();
                if (!stationId || !category?.value || !pickup?.value || !ret?.value) {
                    return null;
                }
                return {
                    pickup_station_id: stationId,
                    vehicle_category_id: category.value,
                    pickup_at: pickup.value,
                    return_at: ret.value,
                };
            }

            function setStatus(message, ok) {
                if (!statusEl) return;
                if (!message) {
                    statusEl.hidden = true;
                    statusEl.textContent = '';
                    return;
                }
                statusEl.hidden = false;
                statusEl.textContent = message;
                statusEl.className = 'text-sm ' + (ok === true ? 'text-success' : ok === false ? 'text-danger' : 'text-muted');
            }

            function checkAvailability() {
                var payload = tripPayload();
                if (!payload) {
                    setStatus(@json(__('body.field_required')), false);
                    return Promise.resolve(false);
                }

                setStatus(@json(__('body.trip_availability_checking')), null);

                return fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                }).then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok) {
                            var err = data.message
                                || (data.errors && Object.values(data.errors)[0] && Object.values(data.errors)[0][0])
                                || @json(__('body.no_category_availability'));
                            setStatus(err, false);
                            return false;
                        }
                        setStatus(data.message || '', !!data.available);
                        return !!data.available;
                    });
                }).catch(function () {
                    setStatus(@json(__('body.no_category_availability')), false);
                    return false;
                });
            }

            function scheduleCheck() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () { checkAvailability(); }, 350);
            }

            ['vehicle_category_id', 'pickup_at', 'return_at', 'pickup_station_id', 'return_station_id'].forEach(function (name) {
                form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
                    el.addEventListener('change', scheduleCheck);
                    el.addEventListener('input', scheduleCheck);
                });
            });
            form.querySelectorAll('[name="pickup_method"], [name="return_method"]').forEach(function (el) {
                el.addEventListener('change', scheduleCheck);
            });

            form._wizardBeforeNext = function (step) {
                if (step !== 4) return true;
                return checkAvailability();
            };

            function formatAmount(value) {
                return Math.round(value).toLocaleString('en-US');
            }

            function selectedCategoryMeta() {
                var select = form.querySelector('[name="vehicle_category_id"]');
                if (!select || !select.value) {
                    return { daily: 0, name: '' };
                }
                var option = select.options[select.selectedIndex];
                return {
                    daily: parseFloat(option.getAttribute('data-daily-price') || '0'),
                    name: option.getAttribute('data-category-name') || option.textContent.trim(),
                };
            }

            function rentalDays() {
                if (!pickupInput?.value || !returnInput?.value) return 0;
                var start = new Date(pickupInput.value);
                var end = new Date(returnInput.value);
                if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return 0;
                return Math.max(1, Math.ceil((end.getTime() - start.getTime()) / 86400000));
            }

            function syncPaymentSummary() {
                if (!summary) return;
                var lines = summary.querySelector('[data-summary-lines]');
                var totalEl = summary.querySelector('[data-summary-total]');
                var daysEl = summary.querySelector('[data-summary-days]');
                if (!lines || !totalEl) return;

                var category = selectedCategoryMeta();
                var chauffeurDaily = parseFloat(summary.getAttribute('data-chauffeur-daily') || '0');
                var deliveryFee = parseFloat(summary.getAttribute('data-delivery-fee') || '0');
                var collectionFee = parseFloat(summary.getAttribute('data-collection-fee') || '0');
                var chauffeurValue = summary.getAttribute('data-chauffeur-value');
                var deliveryValue = summary.getAttribute('data-delivery-value');
                var collectionValue = summary.getAttribute('data-collection-value');
                var dayLabel = summary.getAttribute('data-day-label') || 'day';
                var daysLabel = summary.getAttribute('data-days-label') || 'days';

                var days = rentalDays();
                var isChauffeur = (form.querySelector('[name="rental_type"]:checked') || {}).value === chauffeurValue;
                var isDelivery = (form.querySelector('[name="pickup_method"]:checked') || {}).value === deliveryValue;
                var isCollection = (form.querySelector('[name="return_method"]:checked') || {}).value === collectionValue;

                var html = '';
                var total = 0;

                if (days > 0 && category.daily > 0) {
                    var rental = category.daily * days;
                    total += rental;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink min-w-0">' + category.name + ' <span class="text-muted">× ' + days + ' ' + (days === 1 ? dayLabel : daysLabel) + '</span></span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(rental) + ' RWF</span></div>';
                }

                if (isDelivery && deliveryFee > 0) {
                    total += deliveryFee;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + summary.getAttribute('data-delivery-label') + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(deliveryFee) + ' RWF</span></div>';
                }

                if (isCollection && collectionFee > 0) {
                    total += collectionFee;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + summary.getAttribute('data-collection-label') + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(collectionFee) + ' RWF</span></div>';
                }

                if (isChauffeur && days > 0 && chauffeurDaily > 0) {
                    var chauffeurTotal = chauffeurDaily * days;
                    total += chauffeurTotal;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + summary.getAttribute('data-chauffeur-label') + ' <span class="text-muted">× ' + days + ' ' + (days === 1 ? dayLabel : daysLabel) + '</span></span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(chauffeurTotal) + ' RWF</span></div>';
                }

                form.querySelectorAll('[data-extra-qty]').forEach(function (input) {
                    var qty = parseInt(input.value || '0', 10);
                    if (qty < 1) return;
                    var price = parseFloat(input.getAttribute('data-extra-price') || '0');
                    var name = input.getAttribute('data-extra-name') || 'Extra';
                    var lineTotal = price * qty;
                    total += lineTotal;
                    html += '<div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">'
                        + '<span class="text-ink">' + name + ' × ' + qty + '</span>'
                        + '<span class="shrink-0 text-muted tabular-nums">' + formatAmount(lineTotal) + ' RWF</span></div>';
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

            [pickupInput, returnInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', syncPaymentSummary);
                el.addEventListener('input', syncPaymentSummary);
            });
            form.querySelectorAll('[data-extra-qty]').forEach(function (input) {
                input.addEventListener('input', syncPaymentSummary);
                input.addEventListener('change', syncPaymentSummary);
            });

            form.addEventListener('wizard:step', function (event) {
                if (event.detail && event.detail.isLast) {
                    syncPaymentSummary();
                }
            });

            syncPaymentSummary();

            var paymentSelect = form.querySelector('#payment-option') || form.querySelector('[name="payment_option"]');
            var paymentHint = document.getElementById('payment-option-help');
            var corporateDefault = @json(\App\Enums\PaymentOption::CorporateBilling->value);
            var individualDefault = @json(\App\Enums\PaymentOption::PayAtPickup->value);
            var corporateHint = @json(__('body.payment_option_corporate_default'));
            var individualHint = @json(__('body.payment_option_individual_default'));

            function customerType() {
                return (form.querySelector('[name="customer_type"]:checked') || {}).value || 'individual';
            }

            function syncPaymentOptions(forceDefault) {
                if (!paymentSelect) return;
                var type = customerType();
                var current = paymentSelect.value;
                var preferred = type === 'corporate' ? corporateDefault : individualDefault;
                var firstVisible = null;

                Array.from(paymentSelect.options).forEach(function (option) {
                    if (!option.value) return;
                    var scope = option.getAttribute('data-customer-type') || 'both';
                    var visible = scope === 'both' || scope === type;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible && !firstVisible) firstVisible = option.value;
                });

                var currentOption = paymentSelect.querySelector('option[value="' + CSS.escape(current) + '"]');
                var currentVisible = currentOption && !currentOption.disabled;
                if (forceDefault || !currentVisible) {
                    paymentSelect.value = preferred;
                    if (paymentSelect.value !== preferred && firstVisible) {
                        paymentSelect.value = firstVisible;
                    }
                }

                var selected = paymentSelect.options[paymentSelect.selectedIndex];
                if (paymentHint) {
                    paymentHint.textContent = (selected && selected.getAttribute('data-hint'))
                        || (type === 'corporate' ? corporateHint : individualHint);
                }

                paymentSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            form.addEventListener('change', function (event) {
                if (event.target && event.target.name === 'customer_type') {
                    syncPaymentOptions(true);
                }
                if (event.target && event.target.name === 'payment_option') {
                    var selected = paymentSelect && paymentSelect.options[paymentSelect.selectedIndex];
                    if (paymentHint && selected) {
                        paymentHint.textContent = selected.getAttribute('data-hint')
                            || (customerType() === 'corporate' ? corporateHint : individualHint);
                    }
                }
            });

            syncPaymentOptions(false);
        })();
    </script>
</x-layouts.app>
