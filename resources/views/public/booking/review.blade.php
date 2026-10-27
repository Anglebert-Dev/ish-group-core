@php
    $selectedGateway = old('payment_gateway', \App\Enums\SimulatedPaymentGateway::Card->value);
    $formAction = $formAction ?? route('booking.review.confirm');
    $backHref = $backHref ?? ($vehicle ? route('booking.details', ['vehicle' => $vehicle->uuid]) : route('account.bookings.index'));
    $cancelHref = $cancelHref ?? ($vehicle ? route('vehicles.show', $vehicle) : route('account.bookings.index'));
    $displayName = $displayName ?? $vehicle?->name() ?? __('body.booking');
    $dailyPrice = $dailyPrice ?? $vehicle?->dailyPrice() ?? 0;
@endphp

<x-layouts.public :title="__('body.booking_wizard_payment_title')">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
        <x-common.page-header :title="__('body.booking_wizard_payment_title')" :description="__('body.booking_wizard_payment_description')">
            <x-slot:actions>
                <x-common.button :href="$backHref" variant="secondary">
                    {{ __('body.back') }}
                </x-common.button>
            </x-slot:actions>
        </x-common.page-header>

        <x-common.timezone-note class="mb-5" />

        @unless ($available)
            <div class="bg-red-50 border border-danger/30 text-danger text-sm rounded-lg p-4 mb-5">
                {{ __('body.no_category_availability') }}
            </div>
        @endunless

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8 items-start">
            {{-- Left: booking summary --}}
            <aside class="lg:col-span-2">
                <div class="lg:sticky lg:top-24 rounded-2xl overflow-hidden text-white shadow-card"
                     style="background: linear-gradient(145deg, var(--brand-dark, #0f766e) 0%, var(--brand, #14b8a6) 55%, #0ea5e9 100%);">
                    <div class="p-6 sm:p-7 space-y-6">
                        <div>
                            <p class="text-sm text-white/80">{{ __('body.total_to_pay') }}</p>
                            <p class="font-display font-bold text-4xl tracking-tight mt-1 tabular-nums">
                                {{ number_format((float) $totalAmount, 0) }}
                                <span class="text-lg font-semibold text-white/85">RWF</span>
                            </p>
                            <p class="text-sm text-white/75 mt-2">{{ $displayName }} · {{ $rentalDays }} {{ $rentalDays === 1 ? __('body.day') : __('body.days') }}</p>
                        </div>

                        <div class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $displayName }}</p>
                                    <p class="text-white/70 text-xs mt-0.5">{{ $vehicle?->category?->name ?? $booking?->category?->name }} · {{ number_format((float) $dailyPrice, 0) }} RWF/{{ __('body.day') }}</p>
                                </div>
                                <p class="shrink-0 font-semibold tabular-nums">{{ number_format((float) $categorySubtotal, 0) }}</p>
                            </div>

                            @if ((float) $deliveryFee > 0)
                                <div class="flex items-center justify-between gap-3 text-white/90">
                                    <span>{{ __('body.delivery_fee') }}</span>
                                    <span class="tabular-nums">{{ number_format((float) $deliveryFee, 0) }}</span>
                                </div>
                            @endif
                            @if ((float) $collectionFee > 0)
                                <div class="flex items-center justify-between gap-3 text-white/90">
                                    <span>{{ __('body.collection_fee') }}</span>
                                    <span class="tabular-nums">{{ number_format((float) $collectionFee, 0) }}</span>
                                </div>
                            @endif
                            @if ((float) $chauffeurFee > 0)
                                <div class="flex items-center justify-between gap-3 text-white/90">
                                    <span>{{ __('body.chauffeur_fee') }}</span>
                                    <span class="tabular-nums">{{ number_format((float) $chauffeurFee, 0) }}</span>
                                </div>
                            @endif
                            @foreach ($extraLines as $line)
                                <div class="flex items-center justify-between gap-3 text-white/90">
                                    <span>{{ $line['extra']->name }} × {{ $line['quantity'] }}</span>
                                    <span class="tabular-nums">{{ number_format((float) $line['total'], 0) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-white/20 space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3 text-white/80">
                                <span>{{ __('body.pickup_datetime') }}</span>
                                <span class="text-right">@kigali($pickupAt)</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-white/80">
                                <span>{{ __('body.return_datetime') }}</span>
                                <span class="text-right">@kigali($returnAt)</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 font-semibold pt-2">
                                <span>{{ __('body.total_due_today') }}</span>
                                <span class="tabular-nums text-lg">{{ number_format((float) $totalAmount, 0) }} RWF</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Right: payment form --}}
            <div class="lg:col-span-3">
                <form action="{{ $formAction }}" method="POST" class="bg-white border border-line rounded-2xl p-5 sm:p-7 space-y-7" data-payment-checkout>
                    @csrf

                    <div class="space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('body.payment_gateway') }}</p>
                            <p class="text-xs text-muted mt-0.5">{{ __('body.payment_gateway_hint') }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($paymentGateways as $gateway)
                                @php
                                    $isCard = $gateway === \App\Enums\SimulatedPaymentGateway::Card;
                                    $checked = $selectedGateway === $gateway->value;
                                @endphp
                                <label class="relative flex items-start gap-3 rounded-xl border px-4 py-4 cursor-pointer transition-all duration-150 {{ $checked ? 'border-brand bg-brand-light/40 shadow-sm' : 'border-line bg-white hover:border-brand/50' }}">
                                    <input
                                        type="radio"
                                        name="payment_gateway"
                                        value="{{ $gateway->value }}"
                                        class="sr-only peer"
                                        data-payment-method
                                        @checked($checked)
                                    >
                                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface border border-line text-brand">
                                        @if ($isCard)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-ink">{{ $gateway->label() }}</span>
                                        <span class="block text-xs text-muted mt-0.5">
                                            {{ $isCard ? __('body.pay_with_card_hint') : __('body.pay_with_momo_hint') }}
                                        </span>
                                    </span>
                                    <span class="absolute top-3 right-3 h-5 w-5 rounded-full border flex items-center justify-center {{ $checked ? 'border-brand bg-brand text-white' : 'border-line bg-white text-transparent' }}" data-method-check>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_gateway')
                            <p class="text-xs text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Card fields --}}
                    <div class="space-y-4" data-payment-panel="card" @if ($selectedGateway !== 'card') hidden @endif>
                        <div class="flex items-center gap-2">
                            <img src="{{ asset('assets/images/payments/visa.svg') }}" alt="Visa" class="h-7 w-auto rounded shadow-sm" width="42" height="28">
                            <img src="{{ asset('assets/images/payments/mastercard.svg') }}" alt="Mastercard" class="h-7 w-auto rounded shadow-sm" width="42" height="28">
                            <img src="{{ asset('assets/images/payments/amex.svg') }}" alt="American Express" class="h-7 w-auto rounded shadow-sm" width="42" height="28">
                        </div>

                        <x-common.input
                            name="card_number"
                            :label="__('body.card_number')"
                            :value="old('card_number')"
                            inputmode="numeric"
                            autocomplete="cc-number"
                            placeholder="•••• •••• •••• ••••"
                        />

                        <div class="grid grid-cols-2 gap-4">
                            <x-common.input
                                name="card_expiry"
                                :label="__('body.card_expiry')"
                                :value="old('card_expiry')"
                                autocomplete="cc-exp"
                                placeholder="MM/YY"
                            />
                            <x-common.input
                                name="card_cvc"
                                :label="__('body.card_cvc')"
                                :value="old('card_cvc')"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                placeholder="CVC"
                            />
                        </div>

                        <x-common.input
                            name="card_name"
                            :label="__('body.card_name')"
                            :value="old('card_name')"
                            autocomplete="cc-name"
                            :placeholder="__('body.card_name_placeholder')"
                        />
                    </div>

                    {{-- MoMo fields --}}
                    <div class="space-y-4" data-payment-panel="momo" @if ($selectedGateway !== 'momo') hidden @endif>
                        <x-common.input
                            name="momo_phone"
                            type="tel"
                            :label="__('body.momo_phone')"
                            :value="old('momo_phone')"
                            inputmode="tel"
                            placeholder="07X XXX XXXX"
                            :hint="__('body.momo_phone_hint')"
                        />
                        <x-common.select
                            name="momo_network"
                            :label="__('body.momo_network')"
                            :value="old('momo_network', 'mtn')"
                            :hide-placeholder="true"
                        >
                            <option value="mtn" @selected(old('momo_network', 'mtn') === 'mtn')">MTN MoMo</option>
                            <option value="airtel" @selected(old('momo_network') === 'airtel')">Airtel Money</option>
                        </x-common.select>
                    </div>

                    <div class="rounded-xl border border-line bg-surface px-4 py-3">
                        <p class="text-xs text-muted leading-relaxed">{{ __('body.payment_simulation_note') }}</p>
                    </div>

                    <div class="space-y-3 pt-1">
                        <div class="text-center">
                            <a href="{{ $cancelHref }}" class="text-sm font-semibold text-muted hover:text-brand transition-colors">
                                {{ __('body.cancel') }}
                            </a>
                        </div>
                        @if ($available)
                            <x-common.button type="submit" class="w-full justify-center">
                                {{ __('body.pay_amount_now', ['amount' => number_format((float) $totalAmount, 0)]) }}
                            </x-common.button>
                        @else
                            <x-common.button type="submit" variant="secondary" disabled class="w-full justify-center opacity-50 pointer-events-none">
                                {{ __('body.pay_amount_now', ['amount' => number_format((float) $totalAmount, 0)]) }}
                            </x-common.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.querySelector('[data-payment-checkout]');
            if (!form) return;

            function syncMethodUi() {
                var selected = form.querySelector('[data-payment-method]:checked');
                var value = selected ? selected.value : 'card';

                form.querySelectorAll('[data-payment-method]').forEach(function (input) {
                    var card = input.closest('label');
                    var check = card?.querySelector('[data-method-check]');
                    var on = input.checked;
                    if (!card) return;
                    card.classList.toggle('border-brand', on);
                    card.classList.toggle('bg-brand-light/40', on);
                    card.classList.toggle('shadow-sm', on);
                    card.classList.toggle('border-line', !on);
                    card.classList.toggle('bg-white', !on);
                    if (check) {
                        check.className = 'absolute top-3 right-3 h-5 w-5 rounded-full border flex items-center justify-center '
                            + (on ? 'border-brand bg-brand text-white' : 'border-line bg-white text-transparent');
                    }
                });

                form.querySelectorAll('[data-payment-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-payment-panel') !== value;
                });
            }

            form.querySelectorAll('[data-payment-method]').forEach(function (input) {
                input.addEventListener('change', syncMethodUi);
            });

            var cardNumber = form.querySelector('#field-card-number');
            if (cardNumber) {
                function formatCardNumber() {
                    var digits = (cardNumber.value || '').replace(/\D/g, '').slice(0, 19);
                    cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                }
                cardNumber.addEventListener('input', formatCardNumber);
                formatCardNumber();
            }

            syncMethodUi();
        })();
    </script>
</x-layouts.public>
