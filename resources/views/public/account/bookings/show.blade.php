@php
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    if ($galleryCover || ($galleryImages ?? collect())->isNotEmpty()) {
        $tabs[] = ['id' => 'photos', 'label' => __('body.photos'), 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'];
    }
    if (($canExtend ?? false) || ($canCancel ?? false)) {
        $tabs[] = ['id' => 'actions', 'label' => __('body.actions'), 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'];
    }
@endphp
<x-layouts.public :title="$booking->booking_number" :panel="true">
    <div data-details-page>
        <nav class="text-sm text-muted mb-4 flex items-center gap-2">
            <a href="{{ route('account.bookings.index') }}" class="hover:text-brand">{{ __('body.my_bookings') }}</a> /
            <span class="text-body font-medium">{{ $booking->booking_number }}</span>
        </nav>

        <x-common.details.header
            :title="$booking->booking_number"
            :subtitle="__('body.booking_details')"
            :back-href="route('account.bookings.index')"
            :back-label="__('body.my_bookings')"
        >
            <x-slot:meta>
                <x-common.status :status="$booking->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($canContinuePayment ?? false)
                    <x-common.button :href="route('account.bookings.pay', $booking)" size="sm">
                        {{ __('body.continue_to_payment') }}
                    </x-common.button>
                @endif
                @if ($booking->status !== \App\Enums\BookingStatus::Pending)
                    <x-common.button :href="route('booking.receipt', $booking)" variant="secondary" size="sm" target="_blank" rel="noopener">
                        {{ __('body.view_receipt') }}
                    </x-common.button>
                    <x-common.button :href="route('booking.receipt.pdf', $booking)" variant="ghost" size="sm">
                        {{ __('body.download_invoice') }}
                    </x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        @if ($canContinuePayment ?? false)
            <div class="mb-5 rounded-xl border border-warning/30 bg-amber-50 px-4 py-3 sm:px-5 sm:py-4">
                <p class="text-sm font-semibold text-ink">{{ __('body.pending_booking_action_title') }}</p>
                <p class="text-sm text-muted mt-1">{{ __('body.pending_booking_action_hint') }}</p>
            </div>
        @endif

        <x-common.timezone-note class="mb-5" />

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.vehicle')" :value="$booking->assignedVehicle?->name() ?? $booking->category?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.daily_rate')" :value="number_format((float) $booking->category_daily_price, 0).' RWF/'.__('body.day')" />
            <x-common.details.summary-item :label="__('body.total_amount')" :value="number_format((float) $booking->total_amount, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$booking->status->label()" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" active="overview" />
            </x-slot:tabs>
        </x-common.details.summary>

        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.booking_details')" icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                <x-common.booking-collected-details :booking="$booking" variant="public" />

                @if ($booking->assignedDriver)
                    <div class="pt-4 mt-4 border-t border-line">
                        <x-common.details.field :label="__('body.assigned_driver')">
                            {{ $booking->assignedDriver->fullName() }}
                        </x-common.details.field>
                    </div>
                @endif

                @if ((float) ($booking->outstanding_balance ?? 0) > 0)
                    <div class="pt-4 mt-4 border-t border-line">
                        <x-common.details.field :label="__('body.outstanding_balance')">
                            {{ number_format($booking->outstanding_balance, 0) }} RWF
                        </x-common.details.field>
                    </div>
                @endif

                @if ($booking->status === \App\Enums\BookingStatus::Completed)
                    <div class="pt-4 mt-4 border-t border-line">
                        <x-common.details.grid>
                            <x-common.details.field :label="__('body.extra_days_charge')">
                                {{ number_format($booking->extra_days_charge ?? 0, 0) }} RWF
                            </x-common.details.field>
                            <x-common.details.field :label="($booking->outstanding_balance ?? 0) < 0 ? __('body.refund_due') : __('body.outstanding_balance')">
                                @if (($booking->outstanding_balance ?? 0) < 0)
                                    <span class="text-success">{{ number_format(abs($booking->outstanding_balance), 0) }} RWF</span>
                                @else
                                    {{ number_format($booking->outstanding_balance ?? 0, 0) }} RWF
                                @endif
                            </x-common.details.field>
                        </x-common.details.grid>
                    </div>
                @endif

                @if ($booking->status === \App\Enums\BookingStatus::Cancelled && $booking->cancellation_reason)
                    <div class="pt-4 mt-4 border-t border-line">
                        <x-common.details.field :label="__('body.cancellation_reason')">
                            <span class="whitespace-pre-line">{{ $booking->cancellation_reason }}</span>
                        </x-common.details.field>
                    </div>
                @endif
            </x-common.details.section>
        </div>

        @if ($galleryCover || ($galleryImages ?? collect())->isNotEmpty())
            <div data-details-panel="photos" class="hidden space-y-6">
                <x-common.details.section :title="__('body.photos')" icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" tone="ink">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <p class="font-semibold text-ink truncate">{{ $galleryTitle }}</p>
                        @unless ($booking->assignedVehicle)
                            <p class="text-xs text-muted shrink-0">{{ __('body.category_photos_hint') }}</p>
                        @endunless
                    </div>

                    <div class="rounded-xl overflow-hidden bg-surface border border-line h-48 sm:h-72 flex items-center justify-center">
                        @if ($galleryCover)
                            <img src="{{ $galleryCover }}" class="w-full h-full object-cover" alt="{{ $galleryTitle }}" data-booking-gallery-cover>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"/></svg>
                        @endif
                    </div>

                    @if (($galleryImages ?? collect())->count() > 1)
                        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 sm:gap-3 mt-3">
                            @foreach ($galleryImages as $image)
                                <button
                                    type="button"
                                    class="rounded-lg overflow-hidden bg-surface border border-line h-16 sm:h-20 focus:outline-none focus:ring-2 focus:ring-brand/30"
                                    data-booking-gallery-thumb
                                    data-src="{{ $image->url() }}"
                                >
                                    <img src="{{ $image->url() }}" class="w-full h-full object-cover" alt="{{ $galleryTitle }}">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-common.details.section>
            </div>
        @endif

        @if (($canExtend ?? false) || ($canCancel ?? false))
            <div data-details-panel="actions" class="hidden space-y-6">
                @if ($canExtend ?? false)
                    <x-common.details.section :title="__('body.extend_booking')" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" tone="brand">
                        <p class="text-xs text-muted mb-4">{{ __('body.extend_booking_hint', [
                            'daily' => number_format((float) $booking->category_daily_price, 0),
                            'surcharge' => number_format((float) $extensionDailySurcharge, 0),
                        ]) }}</p>
                        <form action="{{ route('account.bookings.extend', $booking) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-end">
                            @csrf
                            <x-common.datetime-split
                                name="new_return_at"
                                :label="__('body.new_return_datetime')"
                                :date-placeholder="__('body.hero_return_date')"
                                :value="old('new_return_at')"
                                required
                                :min="$booking->return_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')"
                            />
                            <x-common.button type="submit" class="w-full sm:w-auto">{{ __('body.request_extension') }}</x-common.button>
                        </form>
                    </x-common.details.section>
                @endif

                @if ($canCancel ?? false)
                    <x-common.details.section :title="__('body.cancel_booking')" icon="M6 18L18 6M6 6l12 12" tone="danger">
                        <p class="text-xs text-muted mb-4">{{ __('body.customer_cancel_booking_hint') }}</p>
                        <form action="{{ route('account.bookings.cancel', $booking) }}" method="POST" class="space-y-3">
                            @csrf
                            <x-common.textarea name="reason" :label="__('body.cancellation_reason')" :rows="2" :value="old('reason')" />
                            <x-common.button
                                type="submit"
                                variant="danger"
                                class="w-full sm:w-auto"
                                data-confirm
                                data-confirm-title="{{ __('body.cancel_booking') }}?"
                                data-confirm-variant="danger"
                                data-confirm-text="{{ __('body.cancel_booking') }}"
                            >{{ __('body.cancel_booking') }}</x-common.button>
                        </form>
                    </x-common.details.section>
                @endif
            </div>
        @endif

        @if (($galleryImages ?? collect())->count() > 1)
            <script>
                (function () {
                    var cover = document.querySelector('[data-booking-gallery-cover]');
                    if (!cover) return;
                    document.querySelectorAll('[data-booking-gallery-thumb]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var src = btn.getAttribute('data-src');
                            if (src) cover.src = src;
                        });
                    });
                })();
            </script>
        @endif
    </div>
</x-layouts.public>
