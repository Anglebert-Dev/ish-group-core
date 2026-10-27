<x-layouts.public :title="__('body.payment_success_title')">
    @php
        $bookingDetailsUrl = route('account.bookings.show', $booking);
        $dashboardUrl = route('profile');
        $paymentGateway = session('payment_gateway');
        $isPaid = $booking->status === \App\Enums\BookingStatus::Confirmed;
    @endphp

    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-brand-light/70 via-surface to-transparent"></div>

        <div class="relative max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <div class="text-center">
                <div class="relative inline-flex items-center justify-center mb-6">
                    <span class="absolute h-28 w-28 rounded-full bg-success/10 animate-pulse"></span>
                    <span class="relative h-20 w-20 rounded-full bg-success flex items-center justify-center shadow-lg shadow-success/25">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                </div>

                <p class="text-sm font-semibold text-success tracking-wide uppercase">{{ __('body.payment_success_eyebrow') }}</p>
                <h1 class="font-display font-bold text-3xl sm:text-4xl text-ink mt-2">{{ __('body.payment_success_title') }}</h1>
                <p class="text-muted mt-3 max-w-md mx-auto leading-relaxed">
                    {{ __('body.payment_success_subtitle', ['company' => $company]) }}
                </p>
                <p class="text-xs font-semibold tracking-wide text-muted uppercase mt-5">{{ __('body.booking_reference_number') }}</p>
                <p class="font-display font-bold text-2xl sm:text-3xl text-brand mt-1">{{ $booking->booking_number }}</p>
            </div>

            <div id="booking-receipt" class="mt-10 bg-white border border-line rounded-2xl shadow-card overflow-hidden text-left scroll-mt-24">
                <div class="px-5 sm:px-6 py-5 border-b border-line bg-surface/80 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink truncate">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name ?? __('body.booking') }}</p>
                        <p class="text-sm text-muted mt-1">
                            {{ __('body.effective_from_to', [
                                'from' => $booking->pickup_at->timezone(config('app.timezone'))->format('d M Y'),
                                'to' => $booking->return_at->timezone(config('app.timezone'))->format('d M Y'),
                            ]) }}
                        </p>
                        <p class="text-xs text-muted mt-1">
                            {{ $booking->category?->name }}
                            · {{ number_format((float) $booking->category_daily_price, 0) }} RWF/{{ __('body.day') }}
                        </p>
                    </div>
                    <span class="shrink-0 h-8 w-8 rounded-full bg-brand-light text-brand flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                </div>

                <div class="px-5 sm:px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs text-muted">{{ __('body.reference_id') }}</p>
                        <p class="font-semibold text-ink mt-1 break-all">{{ $booking->booking_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">{{ __('body.status') }}</p>
                        <div class="mt-1"><x-common.status :status="$booking->status->label()" /></div>
                    </div>
                    <div>
                        <p class="text-xs text-muted">{{ __('body.pickup_datetime') }}</p>
                        <p class="font-semibold text-ink mt-1">@kigali($booking->pickup_at)</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">{{ __('body.return_datetime') }}</p>
                        <p class="font-semibold text-ink mt-1">@kigali($booking->return_at)</p>
                    </div>
                    @if ($paymentGateway)
                        <div>
                            <p class="text-xs text-muted">{{ __('body.payment_gateway') }}</p>
                            <p class="font-semibold text-ink mt-1">{{ $paymentGateway }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs text-muted">{{ __('body.payment_option') }}</p>
                        <p class="font-semibold text-ink mt-1">{{ $booking->payment_option?->label() ?? __('body.pay_now') }}</p>
                    </div>
                    @if ($booking->rental_type === \App\Enums\RentalType::Chauffeur)
                        <div>
                            <p class="text-xs text-muted">{{ __('body.chauffeur_fee') }}</p>
                            <p class="font-semibold text-ink mt-1">{{ number_format($booking->chauffeur_fee ?? 0, 0) }} RWF</p>
                        </div>
                    @endif
                </div>

                @if ($booking->extras->isNotEmpty())
                    <div class="px-5 sm:px-6 pb-5">
                        <p class="text-xs text-muted uppercase tracking-wide mb-2">{{ __('body.extras') }}</p>
                        <div class="space-y-1.5">
                            @foreach ($booking->extras as $line)
                                <div class="flex items-center justify-between text-sm border border-line rounded-lg px-3 py-2">
                                    <span class="text-ink">{{ $line->extra?->name }} &times; {{ $line->quantity }}</span>
                                    <span class="text-muted tabular-nums">{{ number_format($line->total, 0) }} RWF</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $booking->customerName() }}</p>
                        <p class="text-xs text-muted mt-0.5">
                            @if ($isPaid)
                                {{ __('body.booking_paid_confirmed_note') }}
                            @else
                                {{ __('body.booking_pending_review_note') }}
                            @endif
                        </p>
                    </div>
                    <p class="font-display font-bold text-xl text-ink tabular-nums sm:text-right">
                        {{ __('body.paid_amount', ['amount' => number_format($booking->total_amount, 0)]) }}
                    </p>
                </div>
            </div>

            <div class="mt-10">
                <div class="relative flex items-center justify-center mb-6">
                    <div class="absolute inset-x-0 border-t border-dashed border-line"></div>
                    <span class="relative bg-white px-3 py-1 rounded-full border border-line text-xs font-semibold text-muted">{{ __('body.whats_next') }}</span>
                </div>
                <ol class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center sm:text-left">
                    <li class="rounded-xl border border-line bg-white p-4">
                        <p class="text-xs font-semibold text-brand uppercase tracking-wide">{{ __('body.step_n', ['n' => 1]) }}</p>
                        <p class="text-sm font-semibold text-ink mt-1">{{ __('body.next_step_payment_done') }}</p>
                    </li>
                    <li class="rounded-xl border border-line bg-white p-4">
                        <p class="text-xs font-semibold text-brand uppercase tracking-wide">{{ __('body.step_n', ['n' => 2]) }}</p>
                        <p class="text-sm font-semibold text-ink mt-1">{{ __('body.next_step_vehicle_assigned') }}</p>
                    </li>
                    <li class="rounded-xl border border-line bg-white p-4">
                        <p class="text-xs font-semibold text-brand uppercase tracking-wide">{{ __('body.step_n', ['n' => 3]) }}</p>
                        <p class="text-sm font-semibold text-ink mt-1">{{ __('body.next_step_pickup') }}</p>
                    </li>
                </ol>
            </div>

            <div class="mt-10 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
                <x-common.button :href="route('booking.receipt.pdf', $booking)" class="w-full sm:w-auto">
                    {{ __('body.download_pdf') }}
                </x-common.button>
                <x-common.button :href="$bookingDetailsUrl" variant="secondary" class="w-full sm:w-auto">
                    {{ __('body.go_to_booking_details') }}
                </x-common.button>
                <x-common.button :href="$dashboardUrl" variant="ghost" class="w-full sm:w-auto">
                    {{ __('body.go_to_dashboard') }}
                </x-common.button>
            </div>
        </div>
    </div>

    {{-- Success popup: stays until the user closes it --}}
    <div
        id="payment-success-popup"
        class="fixed inset-0 z-[110] hidden items-center justify-center p-3 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-success-popup-title"
        aria-hidden="true"
        data-success-popup
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom)); padding-top: max(0.75rem, env(safe-area-inset-top));"
    >
        <button type="button" class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" data-success-popup-close aria-label="{{ __('body.close') }}"></button>
        <div
            class="relative w-full max-w-md max-h-[min(92dvh,920px)] flex flex-col bg-white rounded-2xl border border-line shadow-xl translate-y-4 opacity-0 transition-all duration-300 data-[open=true]:translate-y-0 data-[open=true]:opacity-100"
            data-success-popup-panel
        >
            <div class="flex-1 overflow-y-auto overscroll-contain px-5 pt-5 sm:px-8 sm:pt-8 text-center">
                <div class="mx-auto h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-success flex items-center justify-center shadow-lg shadow-success/20 mb-4 sm:mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-8 sm:w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 id="payment-success-popup-title" class="font-display font-bold text-xl sm:text-2xl text-ink">{{ __('body.payment_success_title') }}</h2>
                <p class="text-sm text-muted mt-2 leading-relaxed">{{ __('body.payment_success_popup_body') }}</p>
                <p class="font-display font-bold text-brand text-base sm:text-lg mt-3 sm:mt-4">{{ $booking->booking_number }}</p>
            </div>

            <div class="shrink-0 border-t border-line bg-white px-5 py-4 sm:px-8 sm:py-5 flex flex-col gap-2 rounded-b-2xl">
                <x-common.button
                    :href="route('booking.receipt.pdf', $booking)"
                    class="w-full"
                    target="_blank"
                    rel="noopener"
                >{{ __('body.download_pdf') }}</x-common.button>
                <x-common.button
                    :href="route('booking.receipt', $booking)"
                    variant="secondary"
                    class="w-full"
                    target="_blank"
                    rel="noopener"
                >{{ __('body.view_receipt') }}</x-common.button>
                <x-common.button :href="$bookingDetailsUrl" variant="ghost" class="w-full">{{ __('body.go_to_booking_details') }}</x-common.button>
                <button type="button" class="h-11 rounded-lg text-sm font-semibold text-muted hover:text-brand transition-colors" data-success-popup-close>
                    {{ __('body.close') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var popup = document.getElementById('payment-success-popup');
            var panel = popup && popup.querySelector('[data-success-popup-panel]');
            if (!popup || !panel) return;

            if (popup.parentElement !== document.body) {
                document.body.appendChild(popup);
            }

            function openPopup() {
                popup.classList.remove('hidden');
                popup.classList.add('flex');
                popup.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('overflow-hidden');
                requestAnimationFrame(function () {
                    panel.setAttribute('data-open', 'true');
                });
            }

            function closePopup(event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                panel.removeAttribute('data-open');
                document.documentElement.classList.remove('overflow-hidden');
                setTimeout(function () {
                    popup.classList.add('hidden');
                    popup.classList.remove('flex');
                    popup.setAttribute('aria-hidden', 'true');
                }, 220);
            }

            popup.querySelectorAll('[data-success-popup-close]').forEach(function (el) {
                el.addEventListener('click', closePopup);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !popup.classList.contains('hidden')) {
                    closePopup(e);
                }
            });

            openPopup();
        })();
    </script>
</x-layouts.public>
