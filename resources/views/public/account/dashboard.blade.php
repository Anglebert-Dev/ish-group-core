@php
    $moneyIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    $calendarIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
    $carIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"/></svg>';
    $docIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';

    $relativeDay = function ($date) {
        if (! $date) {
            return '—';
        }
        if ($date->isToday()) {
            return __('body.today');
        }
        if ($date->isTomorrow()) {
            return __('body.tomorrow');
        }

        return $date->format('d M');
    };

    $nextDate = $onHire?->return_at ?? $nextPickup?->pickup_at;
    $nextLabel = $onHire ? __('body.next_return') : __('body.next_pickup');
    $nextHint = $onHire
        ? ($onHire->assignedVehicle?->name() ?? $onHire->category?->name)
        : ($nextPickup?->assignedVehicle?->name() ?? $nextPickup?->category?->name);
@endphp

<x-layouts.public :title="__('body.my_account')" :panel="true">
    <x-common.page-header
        :title="__('body.account_welcome', ['name' => Str::of($customer->name)->before(' ')])"
        :description="__('body.my_account_description')"
    >
        <x-slot:actions>
            <x-common.button href="{{ route('account.bookings.index') }}" variant="secondary" size="sm">{{ __('body.my_bookings') }}</x-common.button>
            <x-common.button href="{{ route('booking.search') }}" size="sm">{{ __('body.book_now') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="space-y-6">
        <p class="rounded-lg border border-line bg-white px-4 py-3 text-sm text-body">
            {{ __('body.account_banner') }}
        </p>

        <x-public.account-quick-actions />

        @if ($hasDraft)
            <x-dashboard.attention-card
                :title="__('body.continue_where_you_stopped')"
                :href="route('booking.review')"
                :link-label="__('body.continue')"
                tone="warning"
            >
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </x-slot:icon>
                <p class="text-sm text-amber-900">{{ __('body.booking_draft_hint') }}</p>
            </x-dashboard.attention-card>
        @endif

        <x-dashboard.attention-card
            :title="__('body.do_this_next')"
            :href="route('contact')"
            :link-label="__('body.need_help')"
            tone="info"
        >
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
            </x-slot:icon>
            @if (count($nextActions) === 0)
                <p class="text-sm text-muted py-2">{{ __('body.nothing_urgent') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($nextActions as $action)
                        <a href="{{ $action['href'] }}" class="flex min-h-[3.25rem] w-full items-center justify-between gap-3 rounded-lg border border-blue-100/80 bg-white/70 p-3 text-left hover:bg-blue-100/50">
                            <span class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ $action['title'] }}</p>
                                <p class="text-xs text-muted">{{ $action['description'] }}</p>
                            </span>
                            <span class="shrink-0 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white">{{ $action['cta'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-dashboard.attention-card>

        <x-dashboard.kpi-group :title="__('body.account_overview')">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </x-slot:icon>
            <x-dashboard.stat-card
                :label="__('body.outstanding_balance')"
                :value="$outstanding > 0 ? number_format($outstanding, 0).' RWF' : __('body.nothing_to_pay')"
                :href="$owing ? route('account.bookings.show', $owing) : route('account.bookings.index')"
                :description="$outstanding > 0 ? __('body.pay_when_you_can') : __('body.you_are_up_to_date')"
                :icon="$moneyIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.current_booking')"
                :value="$currentBooking ? $currentBooking->status->label() : __('body.no_trip_yet')"
                :href="$currentBooking ? route('account.bookings.show', $currentBooking) : route('booking.search')"
                :description="$currentBooking ? $currentBooking->booking_number : __('body.tap_book_a_car')"
                :icon="$calendarIcon"
            />
            <x-dashboard.stat-card
                :label="$nextLabel"
                :value="$nextDate ? $relativeDay($nextDate) : __('body.no_trip_booked')"
                :href="$onHire ? route('account.bookings.show', $onHire) : ($nextPickup ? route('account.bookings.show', $nextPickup) : route('booking.search'))"
                :description="$nextHint ?: __('body.start_from_book_a_car')"
                :icon="$carIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.open_trips')"
                :value="$openCount"
                :href="route('account.bookings.index')"
                :description="__('body.open_trips_hint')"
                :icon="$docIcon"
            />
        </x-dashboard.kpi-group>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 relative bg-white border border-line rounded-xl overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand"></div>
                <div class="flex items-center justify-between px-4 sm:px-5 py-4 border-b border-line gap-3">
                    <p class="font-semibold text-ink">{{ __('body.recent_bookings') }}</p>
                    <a href="{{ route('account.bookings.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                <th class="px-5 py-3 font-semibold">{{ __('body.booking_number') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.pickup_datetime') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($recentBookings as $booking)
                                <tr class="hover:bg-surface">
                                    <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                                        <a href="{{ route('account.bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->booking_number }}</a>
                                    </td>
                                    <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name }}</td>
                                    <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $booking->pickup_at->format('d M') }} – {{ $booking->return_at->format('d M') }}</td>
                                    <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ number_format($booking->total_amount, 0) }} RWF</td>
                                    <td class="px-5 py-3.5"><x-common.status :status="$booking->status->label()" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_recent_activity') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="relative bg-white border border-line rounded-xl p-5">
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand rounded-l-xl"></div>
                <p class="font-semibold text-ink mb-2">{{ __('body.need_help') }}</p>
                <p class="text-sm text-muted mb-4">{{ __('body.need_help_description') }}</p>
                <a href="tel:{{ preg_replace('/\s+/', '', __('body.contact_phone_value')) }}" class="inline-flex min-h-[2.75rem] w-full items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                    {{ __('body.call_us', ['phone' => __('body.contact_phone_value')]) }}
                </a>
                <p class="mt-3 text-sm text-muted">{{ __('body.contact_email_value') }}</p>
                <a href="{{ route('contact') }}" class="mt-4 inline-flex text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.contact') }}</a>
            </div>
        </div>
    </div>
</x-layouts.public>
