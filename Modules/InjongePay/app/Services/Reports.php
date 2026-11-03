<?php

namespace Modules\InjongePay\Services;

use App\Libraries\ListExport;
use App\Libraries\GeneralLibrary;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * Totals for a period, for one business (its own panel) or for all of them
 * (the platform). Read straight from payments, transfers and cash-outs.
 */
class Reports
{
    /**
     * @return array<string, int>
     */
    public function summary(?int $businessId, CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $scope = fn ($q) => $businessId ? $q->where('business_id', $businessId) : $q;

        $payments = $scope(Payment::query()->withoutGlobalScopes())->whereBetween('created_at', $range)
            ->selectRaw("COUNT(*) AS c, COALESCE(SUM(gross_amount), 0) AS g, COALESCE(SUM(fee_amount), 0) AS f, COALESCE(SUM(net_amount), 0) AS n,
                COALESCE(SUM(CASE WHEN payment_status = 'REVERSED' THEN gross_amount ELSE 0 END), 0) AS r,
                COALESCE(SUM(CASE WHEN cleared_at IS NULL AND payment_status != 'REVERSED' THEN net_amount ELSE 0 END), 0) AS u")->first();

        $cashouts = $scope(Cashout::query()->withoutGlobalScopes())->whereBetween('created_at', $range)
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN amount ELSE 0 END), 0) AS paid,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS paid_count,
                COALESCE(SUM(CASE WHEN status NOT IN ('COMPLETED', 'FAILED') THEN amount ELSE 0 END), 0) AS open")->first();

        $wallets = $businessId ? Wallet::query()->withoutGlobalScopes()->where('business_id', $businessId)->pluck('id') : null;
        $transfers = fn (string $column) => (int) Transfer::query()->withoutGlobalScopes()->whereBetween('created_at', $range)
            ->when($wallets, fn ($q) => $q->whereIn($column, $wallets))
            ->when($wallets, fn ($q) => $q->whereNotIn($column === 'from_wallet_id' ? 'to_wallet_id' : 'from_wallet_id', $wallets))
            ->sum('amount');

        $balances = $scope(Wallet::query()->withoutGlobalScopes())->whereNull('internal_role')
            ->selectRaw('COALESCE(SUM(available_balance), 0) AS a, COALESCE(SUM(pending_balance), 0) AS p')->first();

        return [
            'payments_count' => (int) $payments->c, 'payments_gross' => (int) $payments->g, 'payments_fee' => (int) $payments->f,
            'payments_net' => (int) $payments->n, 'payments_reversed' => (int) $payments->r, 'payments_unconfirmed' => (int) $payments->u,
            'transfers_out' => $transfers('from_wallet_id'), 'transfers_in' => $transfers('to_wallet_id'),
            'cashouts_paid' => (int) $cashouts->paid, 'cashouts_paid_count' => (int) $cashouts->paid_count, 'cashouts_open' => (int) $cashouts->open,
            'available' => (int) $balances->a, 'pending' => (int) $balances->p,
        ];
    }

    /** Per business, for the platform's overview. */
    public function byBusiness(CarbonInterface $from, CarbonInterface $to)
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        return DB::table('businesses')
            ->leftJoinSub(Payment::query()->withoutGlobalScopes()->whereBetween('created_at', $range)
                ->selectRaw('business_id, COUNT(*) AS payments, SUM(gross_amount) AS gross, SUM(fee_amount) AS fees')->groupBy('business_id'), 'p', 'p.business_id', '=', 'businesses.id')
            ->leftJoinSub(Cashout::query()->withoutGlobalScopes()->where('status', Cashout::COMPLETED)->whereBetween('created_at', $range)
                ->selectRaw('business_id, SUM(amount) AS paid')->groupBy('business_id'), 'c', 'c.business_id', '=', 'businesses.id')
            ->leftJoinSub(Wallet::query()->withoutGlobalScopes()->whereNull('internal_role')->whereNull('deleted_at')
                ->selectRaw('business_id, SUM(available_balance) AS available, SUM(pending_balance) AS pending')->groupBy('business_id'), 'w', 'w.business_id', '=', 'businesses.id')
            ->where(fn ($q) => $q->whereNotNull('p.business_id')->orWhereNotNull('w.business_id'))
            ->orderByDesc('gross')
            ->get(['businesses.id', 'businesses.uuid', 'businesses.name', 'p.payments', 'p.gross', 'p.fees', 'c.paid', 'w.available', 'w.pending']);
    }

    public function paymentsExport(?int $businessId, CarbonInterface $from, CarbonInterface $to): ListExport
    {
        $rows = Payment::query()->withoutGlobalScopes()->with('wallet')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('id')->get()
            ->map(fn (Payment $p) => [
                GeneralLibrary::formatDateTime($p->created_at), $p->reference, $p->bank_reference, $p->wallet?->merchant_code,
                trim(($p->buyer_name ?? '').' '.($p->buyer_phone ?? '')), $p->channel,
                Money::toDecimal($p->gross_amount), Money::toDecimal($p->fee_amount), Money::toDecimal($p->net_amount), $p->statusLabel(),
            ])->all();

        return ListExport::make(__('injongepay::injongepay.payments').' '.$from->toDateString().' – '.$to->toDateString(), 'injonge-pay-payments', [
            __('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('injongepay::injongepay.gtbank_reference'), __('injongepay::injongepay.merchant_code'),
            __('injongepay::injongepay.payer'), __('injongepay::injongepay.channel'), __('injongepay::injongepay.gross'), __('injongepay::injongepay.fee'),
            __('injongepay::injongepay.net'), __('body.status'),
        ], $rows);
    }
}
