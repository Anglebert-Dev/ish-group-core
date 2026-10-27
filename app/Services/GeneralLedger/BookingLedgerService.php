<?php

namespace App\Services\GeneralLedger;

use App\Enums\PaymentOption;
use App\Models\Booking\Booking;
use App\Services\AccountAssignment\AccountAssignmentService;
use App\Services\BaseService;
use Illuminate\Support\Facades\Schema;

/**
 * Posts booking lifecycle journals (confirm, payment, return charges, reversals).
 */
class BookingLedgerService extends BaseService
{
    public function __construct(
        protected LedgerPoster $poster,
        protected AccountAssignmentService $assignments,
    ) {
    }

    public function postConfirm(Booking $booking): array
    {
        if (! $this->ready()) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $rental = bcmul((string) $booking->category_daily_price, (string) $booking->rental_days, 2);
        $extras = bcadd(
            bcadd(
                bcadd((string) ($booking->extras_total ?? 0), (string) ($booking->chauffeur_fee ?? 0), 2),
                (string) ($booking->delivery_fee ?? 0),
                2
            ),
            (string) ($booking->collection_fee ?? 0),
            2
        );
        $total = (string) $booking->total_amount;

        $ar = $this->assignments->requireAccount('booking_confirm', 'receivable');
        $rentalRevenue = $this->assignments->requireAccount('booking_confirm', 'rental_revenue');
        $extrasRevenue = $this->assignments->requireAccount('booking_confirm', 'extras_revenue');

        $lines = [
            ['account_id' => $ar->id, 'debit' => $total, 'credit' => 0, 'description' => 'AR '.$booking->booking_number],
            ['account_id' => $rentalRevenue->id, 'debit' => 0, 'credit' => $rental, 'description' => 'Rental revenue'],
        ];

        if (bccomp($extras, '0', 2) > 0) {
            $lines[] = ['account_id' => $extrasRevenue->id, 'debit' => 0, 'credit' => $extras, 'description' => 'Extras / chauffeur'];
        }

        // If rental + extras drift from total, adjust rental credit to keep balance.
        $creditSum = array_reduce($lines, fn ($c, $l) => bcadd($c, (string) $l['credit'], 2), '0.00');
        if (bccomp($creditSum, $total, 2) !== 0) {
            $lines[1]['credit'] = bcsub($total, bcsub($creditSum, (string) $lines[1]['credit'], 2), 2);
        }

        $result = $this->poster->post(
            'BK-CONF-'.$booking->uuid,
            'booking_confirm',
            $booking->id,
            now()->toDateString(),
            'Booking confirm '.$booking->booking_number,
            $lines,
        );

        if (! ($result['status'] ?? false)) {
            return $result;
        }

        if ($booking->payment_option === PaymentOption::PayNow) {
            return $this->postPayment($booking, $total, 'confirm');
        }

        return $result;
    }

    public function postPayment(Booking $booking, ?string $amount = null, string $source = 'manual'): array
    {
        if (! $this->ready()) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $amount = $amount ?? (string) $booking->total_amount;
        if (bccomp($amount, '0', 2) <= 0) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $cash = $this->assignments->requireAccount('booking_payment', 'cash');
        $ar = $this->assignments->requireAccount('booking_payment', 'receivable');

        return $this->poster->post(
            'BK-PAY-'.$booking->uuid.($source !== 'manual' ? '-'.$source : ''),
            'booking_payment',
            $booking->id,
            now()->toDateString(),
            'Booking payment '.$booking->booking_number,
            [
                ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    public function postReturnCharges(Booking $booking): array
    {
        if (! $this->ready()) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $amount = (string) ($booking->outstanding_balance ?? 0);
        if (bccomp($amount, '0', 2) <= 0) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $ar = $this->assignments->requireAccount('booking_return_charges', 'receivable');
        $revenue = $this->assignments->requireAccount('booking_return_charges', 'other_revenue');

        return $this->poster->post(
            'BK-RET-'.$booking->uuid,
            'booking_return_charges',
            $booking->id,
            now()->toDateString(),
            'Return charges '.$booking->booking_number,
            [
                ['account_id' => $ar->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    public function reverseBooking(Booking $booking): array
    {
        if (! $this->ready()) {
            return $this->success(null, __('body.ledger_skipped'));
        }

        $this->poster->reverseByReference('BK-PAY-'.$booking->uuid);
        $this->poster->reverseByReference('BK-PAY-'.$booking->uuid.'-confirm');
        $this->poster->reverseByReference('BK-PAY-'.$booking->uuid.'-pickup');
        $this->poster->reverseByReference('BK-RET-'.$booking->uuid);

        return $this->poster->reverseByReference('BK-CONF-'.$booking->uuid);
    }

    protected function ready(): bool
    {
        return Schema::hasTable('general_ledgers') && Schema::hasTable('account_assignments');
    }
}
