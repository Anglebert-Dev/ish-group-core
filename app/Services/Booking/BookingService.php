<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverStatus;
use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\NotificationType;
use App\Enums\PickupMethod;
use App\Enums\RentalType;
use App\Enums\ReturnMethod;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingExtra;
use App\Models\Driver\Driver;
use App\Models\Extra\Extra;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use App\Repositories\Booking\BookingRepository;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Services\Availability\VehicleAvailabilityService;
use App\Services\BaseService;
use App\Services\Extra\ExtraService;
use App\Services\GeneralLedger\BookingLedgerService;
use App\Services\Invoice\InvoiceService;
use App\Services\Licensing\LicenseEligibilityService;
use App\Services\Notification\NotificationService;
use App\Enums\PaymentOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingService extends BaseService
{
    public function __construct(
        protected BookingRepository $bookings,
        protected VehicleAvailabilityService $availability,
        protected LicenseEligibilityService $eligibility,
        protected ExtraService $extras,
        protected BookingLedgerService $bookingLedger,
        protected NotificationService $notifications,
        protected InvoiceService $invoices,
    ) {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'category', 'pickupStation', 'returnStation'])
            ->latest()
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->bookings->query()
            ->with(['customer', 'corporateCustomer', 'category'])
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => [
                $booking->booking_number,
                $booking->customerName(),
                $booking->category?->name ?? '—',
                GeneralLibrary::formatDateTime($booking->pickup_at),
                GeneralLibrary::formatDateTime($booking->return_at),
                number_format((float) $booking->total_amount, 0).' RWF',
                $booking->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.bookings'), 'bookings', [
            __('body.booking_number'),
            __('body.customer'),
            __('body.category'),
            __('body.pickup_datetime'),
            __('body.return_datetime'),
            __('body.total_amount'),
            __('body.status'),
        ], $rows);
    }

    /**
     * @param  array<int, int>  $extras  extra_id => quantity
     */
    public function create(array $data, array $extras = []): array
    {
        $pickupAt = GeneralLibrary::parseAppDateTime($data['pickup_at']);
        $returnAt = GeneralLibrary::parseAppDateTime($data['return_at']);

        if ($pickupAt->lt(GeneralLibrary::earliestPickupAt())) {
            return $this->failure(__('body.pickup_must_be_1_hour_ahead'));
        }

        $rentalDays = max(1, $pickupAt->diffInDays($returnAt));

        $category = VehicleCategory::findOrFail($data['vehicle_category_id']);

        $assignedVehicle = null;
        $dailyPrice = $this->availability->quotedDailyPriceForCategory(
            $category,
            $pickupAt,
            $returnAt,
            (int) $data['pickup_station_id'],
        ) ?? $category->quoteDailyPrice();

        if (! empty($data['assigned_vehicle_id'])) {
            $assignedVehicle = Vehicle::findOrFail($data['assigned_vehicle_id']);

            if ((int) $assignedVehicle->vehicle_category_id !== (int) $category->id) {
                return $this->failure(__('body.vehicle_wrong_category'));
            }

            $dailyPrice = (string) ($assignedVehicle->dailyPrice() ?? $dailyPrice);
        }

        $extraLines = $this->resolveExtraLines($extras);
        $extrasTotal = array_reduce($extraLines, fn ($carry, $line) => bcadd($carry, $line['total'], 2), '0.00');

        $rentalType = $data['rental_type'] instanceof RentalType
            ? $data['rental_type']
            : RentalType::from((string) $data['rental_type']);
        $chauffeurFee = $this->chauffeurFeeFor($rentalType, $rentalDays);

        $pickupMethod = $data['pickup_method'] ?? PickupMethod::Station->value;
        $pickupMethod = $pickupMethod instanceof PickupMethod
            ? $pickupMethod
            : PickupMethod::from((string) $pickupMethod);
        $deliveryFee = $this->deliveryFeeFor($pickupMethod);

        $returnMethod = $data['return_method'] ?? ReturnMethod::Station->value;
        $returnMethod = $returnMethod instanceof ReturnMethod
            ? $returnMethod
            : ReturnMethod::from((string) $returnMethod);
        $collectionFee = $this->collectionFeeFor($returnMethod);

        if ($pickupMethod === PickupMethod::Delivery && blank($data['delivery_address'] ?? null)) {
            return $this->failure(__('body.delivery_address_required'));
        }

        if ($returnMethod === ReturnMethod::Collection && blank($data['collection_address'] ?? null)) {
            return $this->failure(__('body.collection_address_required'));
        }

        if ($pickupMethod === PickupMethod::Station) {
            $data['delivery_address'] = null;
        }

        if ($returnMethod === ReturnMethod::Station) {
            $data['collection_address'] = null;
        }

        $categorySubtotal = bcmul($dailyPrice, (string) $rentalDays, 2);
        $totalAmount = bcadd(
            bcadd(bcadd(bcadd($categorySubtotal, $extrasTotal, 2), $chauffeurFee, 2), $deliveryFee, 2),
            $collectionFee,
            2
        );

        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        $forStaff = Auth::guard('staff')->check();

        try {
            $booking = DB::transaction(function () use (
                $data,
                $extraLines,
                $pickupAt,
                $returnAt,
                $category,
                $assignedVehicle,
                $pickupMethod,
                $returnMethod,
                $rentalDays,
                $dailyPrice,
                $extrasTotal,
                $chauffeurFee,
                $deliveryFee,
                $collectionFee,
                $totalAmount,
                $customerId,
                $forStaff,
            ) {
                if ($assignedVehicle) {
                    // Serialize bookings for this car so two users cannot claim the same slot.
                    $assignedVehicle = Vehicle::query()
                        ->whereKey($assignedVehicle->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $this->availability->isAvailable($assignedVehicle, $pickupAt, $returnAt)) {
                        throw new \RuntimeException(
                            $this->availability->unavailableMessage(
                                $assignedVehicle,
                                $pickupAt,
                                $returnAt,
                                $customerId,
                                null,
                                $forStaff,
                            )
                        );
                    }
                } elseif (! $this->availability->categoryIsAvailable(
                    $category,
                    $pickupAt,
                    $returnAt,
                    (int) $data['pickup_station_id'],
                )) {
                    throw new \RuntimeException(__('body.no_category_availability'));
                }

                $booking = new Booking([
                    ...$data,
                    'pickup_method' => $pickupMethod,
                    'return_method' => $returnMethod,
                    'rental_days' => $rentalDays,
                    'category_daily_price' => $dailyPrice,
                    'assigned_vehicle_id' => $assignedVehicle?->id,
                    'extras_total' => $extrasTotal,
                    'chauffeur_fee' => $chauffeurFee,
                    'delivery_fee' => $deliveryFee,
                    'collection_fee' => $collectionFee,
                    'total_amount' => $totalAmount,
                ]);
                $booking->booking_number = $this->generateBookingNumber();
                $booking->status = BookingStatus::Pending;

                $this->bookings->save($booking);

                foreach ($extraLines as $line) {
                    BookingExtra::create([...$line, 'booking_id' => $booking->id]);
                }

                return $booking;
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($booking, __('body.booking_created'));
    }

    /**
     * @param  array<int, int>  $extras  extra_id => quantity
     * @return array<int, array{extra_id: int, quantity: int, unit_price: string, total: string}>
     */
    protected function resolveExtraLines(array $extras): array
    {
        $extras = array_filter($extras, fn ($quantity) => (int) $quantity > 0);

        if (empty($extras)) {
            return [];
        }

        $catalog = Extra::whereIn('id', array_keys($extras))
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Optional->value)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($extras as $extraId => $quantity) {
            $extra = $catalog->get((int) $extraId);

            if (! $extra) {
                continue;
            }

            $quantity = (int) $quantity;

            $lines[] = [
                'extra_id' => $extra->id,
                'quantity' => $quantity,
                'unit_price' => $extra->price,
                'total' => bcmul((string) $extra->price, (string) $quantity, 2),
            ];
        }

        return $lines;
    }

    public function confirm(Booking $booking): array
    {
        if ($booking->status !== BookingStatus::Pending) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        try {
            DB::transaction(function () use ($booking) {
                $conflict = $this->assignedVehicleConflictMessage($booking);
                if ($conflict !== null) {
                    throw new \RuntimeException($conflict);
                }

                $booking->status = BookingStatus::Confirmed;
                $this->bookings->save($booking);
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        $this->bookingLedger->postConfirm($booking->fresh());

        $booking = $booking->fresh();

        if ($booking->payment_option === PaymentOption::CorporateBilling) {
            $this->invoices->createFromBooking($booking);
        }

        if (Auth::guard('customer')->check() && $booking->customer_id) {
            $this->notifyStaffOfCustomerBooking($booking);
        }

        return $this->success($booking, __('body.booking_confirmed'));
    }

    protected function notifyStaffOfCustomerBooking(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'category', 'assignedVehicle']);

        $vehicleLabel = $booking->assignedVehicle?->name()
            ?? $booking->category?->name
            ?? '—';

        $this->notifications->notifyByPermission(
            'list bookings.booking',
            __('body.notification_customer_booking_title', [
                'number' => $booking->booking_number,
            ]),
            __('body.notification_customer_booking_body', [
                'customer' => $booking->customerName(),
                'vehicle' => $vehicleLabel,
                'amount' => number_format((float) $booking->total_amount, 0),
            ]),
            NotificationType::Booking,
            route('bookings.show', $booking, false),
            $booking,
        );
    }

    public function cancel(Booking $booking, ?string $reason): array
    {
        if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::ReadyForPickup], true)) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        $wasConfirmed = in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::ReadyForPickup], true);

        $booking->status = BookingStatus::Cancelled;
        $booking->cancellation_reason = $reason;
        $this->bookings->save($booking);

        if ($wasConfirmed) {
            $this->bookingLedger->reverseBooking($booking);
        }

        return $this->success($booking, __('body.booking_cancelled'));
    }

    public function markNoShow(Booking $booking): array
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::ReadyForPickup], true)) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        $booking->status = BookingStatus::NoShow;
        $this->bookings->save($booking);

        $this->bookingLedger->reverseBooking($booking);

        return $this->success($booking, __('body.booking_no_show'));
    }

    public function assignVehicle(Booking $booking, Vehicle $vehicle): array
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        if ($vehicle->vehicle_category_id !== $booking->vehicle_category_id) {
            return $this->failure(__('body.vehicle_wrong_category'));
        }

        if ($booking->rental_type === RentalType::SelfDrive) {
            if (! $booking->driver_license_category || ! $vehicle->required_license_category) {
                return $this->failure(__('body.licence_not_eligible'));
            }

            if (! $this->eligibility->isEligible($booking->driver_license_category, $vehicle->required_license_category)) {
                return $this->failure(__('body.licence_not_eligible'));
            }
        }

        try {
            DB::transaction(function () use ($booking, $vehicle) {
                $locked = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

                if (! $this->availability->isAvailable(
                    $locked,
                    $booking->pickup_at,
                    $booking->return_at,
                    $booking->id,
                )) {
                    throw new \RuntimeException(
                        $this->availability->unavailableMessage(
                            $locked,
                            $booking->pickup_at,
                            $booking->return_at,
                            $booking->customer_id ? (int) $booking->customer_id : null,
                            $booking->id,
                            Auth::guard('staff')->check(),
                        )
                    );
                }

                $booking->assigned_vehicle_id = $locked->id;
                $booking->category_daily_price = (string) ($locked->dailyPrice() ?? $booking->category_daily_price);

                $categorySubtotal = bcmul((string) $booking->category_daily_price, (string) $booking->rental_days, 2);
                $booking->total_amount = bcadd(
                    bcadd(
                        bcadd(
                            bcadd($categorySubtotal, (string) ($booking->extras_total ?? 0), 2),
                            (string) ($booking->chauffeur_fee ?? 0),
                            2
                        ),
                        (string) ($booking->delivery_fee ?? 0),
                        2
                    ),
                    (string) ($booking->collection_fee ?? 0),
                    2
                );

                $this->bookings->save($booking);
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($booking->fresh(), __('body.vehicle_assigned'));
    }

    public function assignDriver(Booking $booking, Driver $driver): array
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        if ($booking->rental_type !== RentalType::Chauffeur) {
            return $this->failure(__('body.driver_not_applicable'));
        }

        $vehicle = $booking->assignedVehicle;

        if (! $vehicle) {
            return $this->failure(__('body.assign_vehicle_first'));
        }

        if ($driver->status !== DriverStatus::Active || $driver->availability_status !== DriverAvailabilityStatus::Available) {
            return $this->failure(__('body.driver_not_available'));
        }

        if (! $driver->license_category || ! $vehicle->required_license_category) {
            return $this->failure(__('body.licence_not_eligible'));
        }

        if (! $this->eligibility->isEligible($driver->license_category, $vehicle->required_license_category)) {
            return $this->failure(__('body.licence_not_eligible'));
        }

        $categorySubtotal = bcmul((string) $booking->category_daily_price, (string) $booking->rental_days, 2);
        $chauffeurFee = (string) ($booking->chauffeur_fee ?? '0');

        if (bccomp($chauffeurFee, '0.00', 2) === 0) {
            $chauffeurFee = bcmul((string) ($driver->daily_rate ?? 0), (string) $booking->rental_days, 2);
        }

        $booking->assigned_driver_id = $driver->id;
        $booking->chauffeur_fee = $chauffeurFee;
        $booking->total_amount = bcadd(
            bcadd(bcadd(bcadd($categorySubtotal, (string) $booking->extras_total, 2), $chauffeurFee, 2), (string) ($booking->delivery_fee ?? 0), 2),
            (string) ($booking->collection_fee ?? 0),
            2
        );
        $this->bookings->save($booking);

        return $this->success($booking, __('body.driver_assigned'));
    }

    public function markReadyForPickup(Booking $booking): array
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return $this->failure(__('body.booking_invalid_transition'));
        }

        if (! $booking->assigned_vehicle_id) {
            return $this->failure(__('body.assign_vehicle_first'));
        }

        if ($booking->rental_type === RentalType::Chauffeur && ! $booking->assigned_driver_id) {
            return $this->failure(__('body.assign_driver_first'));
        }

        try {
            DB::transaction(function () use ($booking) {
                $conflict = $this->assignedVehicleConflictMessage($booking);
                if ($conflict !== null) {
                    throw new \RuntimeException($conflict);
                }

                $booking->status = BookingStatus::ReadyForPickup;
                $this->bookings->save($booking);
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($booking->fresh(), __('body.booking_ready_for_pickup'));
    }

    /**
     * Lock the assigned vehicle (if any) and return a conflict message when the
     * booking window is no longer free for that unit.
     */
    protected function assignedVehicleConflictMessage(Booking $booking): ?string
    {
        if (! $booking->assigned_vehicle_id) {
            return null;
        }

        $locked = Vehicle::query()
            ->whereKey($booking->assigned_vehicle_id)
            ->lockForUpdate()
            ->first();

        if (! $locked || ! $this->availability->isAvailable(
            $locked,
            $booking->pickup_at,
            $booking->return_at,
            $booking->id,
        )) {
            if (! $locked) {
                return __('body.vehicle_unavailable_for_dates');
            }

            return $this->availability->unavailableMessage(
                $locked,
                $booking->pickup_at,
                $booking->return_at,
                $booking->customer_id ? (int) $booking->customer_id : null,
                $booking->id,
                Auth::guard('staff')->check(),
            );
        }

        return null;
    }

    /**
     * Customer/staff extension: move return_at later and charge normal daily
     * rate plus the staff-configured extension surcharge per extra billable day.
     */
    public function extend(Booking $booking, string $newReturnAt): array
    {
        $allowed = [
            BookingStatus::Confirmed,
            BookingStatus::ReadyForPickup,
            BookingStatus::ActiveRental,
        ];

        if (! in_array($booking->status, $allowed, true)) {
            return $this->failure(__('body.booking_cannot_extend'));
        }

        $newReturn = GeneralLibrary::parseAppDateTime($newReturnAt);

        if ($newReturn->lte($booking->return_at)) {
            return $this->failure(__('body.extension_must_be_after_return'));
        }

        $extensionStart = $booking->return_at->copy()->addMinute();

        if ($booking->assigned_vehicle_id) {
            $assigned = Vehicle::query()->find($booking->assigned_vehicle_id);

            if (! $assigned || ! $this->availability->isAvailable(
                $assigned,
                $extensionStart,
                $newReturn,
                $booking->id,
            )) {
                return $this->failure(__('body.vehicle_unavailable_for_dates'));
            }
        } elseif (! $this->availability->categoryIsAvailable(
            $booking->category,
            $extensionStart,
            $newReturn,
            (int) $booking->pickup_station_id,
            $booking->id,
        )) {
            return $this->failure(__('body.no_category_availability'));
        }

        $extraDays = max(1, (int) ceil($booking->return_at->diffInSeconds($newReturn) / 86400));
        $daily = (string) $booking->category_daily_price;
        $surcharge = $this->extras->extensionDailySurcharge();
        $perDay = bcadd($daily, $surcharge, 2);
        $extensionCharge = bcmul($perDay, (string) $extraDays, 2);

        $previousReturn = $booking->return_at->copy();
        $booking->return_at = $newReturn;
        $booking->rental_days = max(1, $booking->pickup_at->diffInDays($newReturn));
        $booking->chauffeur_fee = $this->chauffeurFeeFor($booking->rental_type, (int) $booking->rental_days);

        $categorySubtotal = bcmul($daily, (string) $booking->rental_days, 2);
        $booking->total_amount = bcadd(
            bcadd(bcadd(bcadd($categorySubtotal, (string) ($booking->extras_total ?? 0), 2), (string) $booking->chauffeur_fee, 2), (string) ($booking->delivery_fee ?? 0), 2),
            (string) ($booking->collection_fee ?? 0),
            2
        );
        $booking->outstanding_balance = bcadd((string) ($booking->outstanding_balance ?? 0), $extensionCharge, 2);
        $booking->notes = trim((string) $booking->notes."\n".__(
            'body.extension_note',
            [
                'from' => GeneralLibrary::formatDateTime($previousReturn),
                'to' => GeneralLibrary::formatDateTime($newReturn),
                'days' => $extraDays,
                'amount' => number_format((float) $extensionCharge, 0),
            ]
        ));

        $this->bookings->save($booking);

        return $this->success($booking, __('body.booking_extended', [
            'days' => $extraDays,
            'amount' => number_format((float) $extensionCharge, 0),
        ]));
    }

    public function chauffeurFeeFor(RentalType $rentalType, int $rentalDays): string
    {
        if ($rentalType !== RentalType::Chauffeur) {
            return '0.00';
        }

        return bcmul($this->extras->chauffeurDailyFee(), (string) max(1, $rentalDays), 2);
    }

    public function deliveryFeeFor(PickupMethod $pickupMethod): string
    {
        if ($pickupMethod !== PickupMethod::Delivery) {
            return '0.00';
        }

        return $this->extras->deliveryFee();
    }

    public function collectionFeeFor(ReturnMethod $returnMethod): string
    {
        if ($returnMethod !== ReturnMethod::Collection) {
            return '0.00';
        }

        return $this->extras->collectionFee();
    }

    protected function generateBookingNumber(): string
    {
        $prefix = 'AFR-'.now()->format('Ym').'-';
        $count = Booking::withTrashed()->where('booking_number', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
