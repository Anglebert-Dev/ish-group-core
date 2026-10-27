<?php

namespace App\Services\Customer;

use App\Enums\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Customer;
use Illuminate\Support\Collection;

class CustomerPortalService
{
    private const OPEN_STATUSES = [
        BookingStatus::Pending->value,
        BookingStatus::Confirmed->value,
        BookingStatus::ReadyForPickup->value,
        BookingStatus::ActiveRental->value,
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Customer $customer): array
    {
        $openBookings = Booking::query()
            ->with(['category', 'assignedVehicle'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderBy('pickup_at')
            ->get();

        $onHire = $openBookings->first(fn (Booking $booking) => $booking->status === BookingStatus::ActiveRental);
        $nextPickup = $openBookings->first(function (Booking $booking) {
            return in_array($booking->status, [
                BookingStatus::Pending,
                BookingStatus::Confirmed,
                BookingStatus::ReadyForPickup,
            ], true) && $booking->pickup_at?->gte(now()->startOfDay());
        });
        $current = $onHire ?? $openBookings->first();

        $owing = Booking::query()
            ->with(['category'])
            ->where('customer_id', $customer->id)
            ->where('outstanding_balance', '>', 0)
            ->orderByDesc('outstanding_balance')
            ->first();

        $outstanding = (float) Booking::query()
            ->where('customer_id', $customer->id)
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');

        $recentBookings = Booking::query()
            ->with(['category', 'assignedVehicle'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->take(8)
            ->get();

        return [
            'currentBooking' => $current,
            'nextPickup' => $nextPickup,
            'onHire' => $onHire,
            'owing' => $owing,
            'outstanding' => $outstanding,
            'recentBookings' => $recentBookings,
            'bookingCount' => Booking::query()->where('customer_id', $customer->id)->count(),
            'openCount' => $openBookings->count(),
            'nextActions' => $this->nextActions($customer, $owing, $nextPickup, $onHire, $recentBookings),
            'hasDraft' => session()->has('booking_draft'),
        ];
    }

    /**
     * @return list<array{title: string, description: string, cta: string, href: string}>
     */
    private function nextActions(
        Customer $customer,
        ?Booking $owing,
        ?Booking $nextPickup,
        ?Booking $onHire,
        Collection $recentBookings,
    ): array {
        $actions = [];

        if ($owing) {
            $actions[] = [
                'title' => __('body.pay_outstanding'),
                'description' => __('body.pay_outstanding_hint', [
                    'amount' => number_format((float) $owing->outstanding_balance, 0),
                    'number' => $owing->booking_number,
                ]),
                'cta' => __('body.see_booking'),
                'href' => route('account.bookings.show', $owing),
            ];
        }

        if ($nextPickup) {
            $actions[] = [
                'title' => __('body.prepare_for_pickup'),
                'description' => __('body.prepare_for_pickup_hint', [
                    'number' => $nextPickup->booking_number,
                    'when' => $this->relativeDay($nextPickup->pickup_at),
                ]),
                'cta' => __('body.see_booking'),
                'href' => route('account.bookings.show', $nextPickup),
            ];
        }

        if ($onHire) {
            $actions[] = [
                'title' => __('body.return_reminder'),
                'description' => __('body.return_reminder_hint', [
                    'number' => $onHire->booking_number,
                    'when' => $this->relativeDay($onHire->return_at),
                ]),
                'cta' => __('body.see_booking'),
                'href' => route('account.bookings.show', $onHire),
            ];
        }

        if (blank($customer->phone)) {
            $actions[] = [
                'title' => __('body.finish_your_profile'),
                'description' => __('body.finish_your_profile_hint'),
                'cta' => __('body.continue'),
                'href' => route('account.profile'),
            ];
        }

        if ($recentBookings->isEmpty()) {
            $actions[] = [
                'title' => __('body.book_a_car'),
                'description' => __('body.book_a_car_empty_hint'),
                'cta' => __('body.book_now'),
                'href' => route('booking.search'),
            ];
        }

        return $actions;
    }

    private function relativeDay(mixed $date): string
    {
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
    }
}
