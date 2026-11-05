<?php

namespace Modules\InjongePay\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\BankTransaction;
use Modules\InjongePay\Models\DailyClose;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;

/**
 * Do our books agree with GTBank? Checked per merchant code and day (the
 * daily close), per wallet (entries add up to the balance) and over the whole
 * platform (every franc GTBank credited is in exactly one place).
 */
class Reconciliation
{
    // A payment GTBank hasn't confirmed after this long is a difference.
    public const UNCONFIRMED_AFTER_HOURS = 24;

    public function __construct(protected BankMirror $mirror, protected Ledger $ledger, protected ReconciliationItems $items)
    {
    }

    /**
     * Closes the day for every merchant code with one read of GTBank's list.
     *
     * @return array{closed: int, unreachable: bool}
     */
    public function closeDayForAll(CarbonInterface $day): array
    {
        $day = Carbon::parse($day->toDateString());
        $pulled = GtbankClient::isConfigured() ? $this->mirror->pullAll($day, $day) : [];
        $closed = 0;

        foreach (Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->whereIn('bank_status', [Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE, Wallet::BANK_CLOSED])->orderBy('id')->cursor() as $wallet) {
            $this->closeDay($wallet, $day, $pulled);
            $closed++;
        }

        return ['closed' => $closed, 'unreachable' => $pulled === null];
    }

    /** @param  array|null|false  $pulled  GTBank's list already read for this day (null: GTBank didn't answer); false: read it for this code */
    public function closeDay(Wallet $wallet, CarbonInterface $day, array|null|false $pulled = false): DailyClose
    {
        $day = Carbon::parse($day->toDateString());

        if ($pulled === false) {
            $pulled = GtbankClient::isConfigured() ? $this->mirror->pull($wallet, $day->copy()->startOfDay(), $day->copy()->endOfDay()) : [];
        }

        $bank = BankTransaction::query()->where('wallet_id', $wallet->id)->where('status', 'SUCCESS')->whereDate('value_date', $day)
            ->where(fn ($q) => $q->whereNull('match_state')->orWhere('match_state', '!=', BankMirror::BEFORE_TAKEOVER))->get(['transaction_id', 'amount']);
        $ours = Payment::query()->withoutGlobalScopes()->where('wallet_id', $wallet->id)->where('payment_status', '!=', Payment::STATUS_REVERSED)
            ->where(fn ($q) => $q->whereDate('value_date', $day)->orWhere(fn ($w) => $w->whereNull('value_date')->whereDate('created_at', $day)))
            ->get(['id', 'bank_reference', 'gross_amount']);

        $differences = [];

        if (GtbankClient::isConfigured() && $pulled !== null) {
            foreach ($ours->whereNotIn('bank_reference', $bank->pluck('transaction_id')) as $payment) {
                $differences[] = ['kind' => 'missing_at_bank', 'reference' => $payment->bank_reference, 'amount' => $payment->gross_amount];
                $this->items->open('missing_at_bank', $payment->bank_reference, $wallet, Payment::query()->withoutGlobalScopes()->find($payment->id), $payment->gross_amount, ['day' => $day->toDateString()]);
            }

            foreach ($bank->whereNotIn('transaction_id', $ours->pluck('bank_reference')) as $tx) {
                $differences[] = ['kind' => 'missing_ours', 'reference' => $tx->transaction_id, 'amount' => $tx->amount];
            }
        }

        $state = match (true) {
            GtbankClient::isConfigured() && $pulled === null => DailyClose::BANK_UNREACHABLE,
            $differences !== [] || (GtbankClient::isConfigured() && (int) $bank->sum('amount') !== (int) $ours->sum('gross_amount')) => DailyClose::DIFFERENCES,
            default => DailyClose::RECONCILED,
        };

        $close = DailyClose::query()->updateOrCreate(['wallet_id' => $wallet->id, 'day' => $day->toDateString()], [
            'business_id' => $wallet->business_id,
            'bank_count' => $bank->count(), 'bank_total' => (int) $bank->sum('amount'),
            'our_count' => $ours->count(), 'our_total' => (int) $ours->sum('gross_amount'),
            'state' => $state, 'differences' => $differences,
        ]);

        audit('injongepay_day_closed', __('injongepay::injongepay.audit_day_closed', ['code' => $wallet->merchant_code, 'day' => $day->toDateString(), 'state' => __('injongepay::injongepay.close_'.$state)]),
            $wallet, ['state' => $state, 'bank_total' => $close->bank_total, 'our_total' => $close->our_total], $wallet->business_id);

        return $close;
    }

    /**
     * Entries add up to every balance; every franc credited is somewhere.
     *
     * @return array{wallets_checked: int, drifts: int, unconfirmed: int, platform_difference: int}
     */
    public function checkBooks(): array
    {
        $drifts = 0;
        $wallets = Wallet::query()->withoutGlobalScopes()->get();

        foreach ($wallets as $wallet) {
            $sum = $this->ledger->recompute($wallet);

            if ($sum['pending'] !== $wallet->pending_balance || $sum['available'] !== $wallet->available_balance) {
                $drifts++;
                $this->items->open('balance_drift', (string) $wallet->id, $wallet, $wallet, $sum['pending'] + $sum['available'] - $wallet->balance(), [
                    'stored' => ['pending' => $wallet->pending_balance, 'available' => $wallet->available_balance], 'entries' => $sum,
                ]);
            }
        }

        $unconfirmed = 0;
        if (GtbankClient::isConfigured()) {
            foreach (Payment::query()->withoutGlobalScopes()->whereNull('cleared_at')->where('payment_status', Payment::STATUS_AVAILABLE)->where('created_at', '<', now()->subHours(self::UNCONFIRMED_AFTER_HOURS))->get() as $payment) {
                $unconfirmed++;
                $this->items->open('unconfirmed_by_bank', $payment->bank_reference, $wallets->firstWhere('id', $payment->wallet_id), $payment, $payment->gross_amount);
            }
        }

        $difference = $this->platformDifference();
        if ($difference !== 0) {
            $this->items->open('platform_difference', now()->toDateString(), null, null, $difference, ['explain' => 'credited − (balances + fees not yet credited + money out)']);
        }

        return ['wallets_checked' => $wallets->count(), 'drifts' => $drifts, 'unconfirmed' => $unconfirmed, 'platform_difference' => $difference];
    }

    /**
     * Σ gross of payments not reversed − (Σ every wallet's balance + fees accrued and not yet credited + money that left).
     * Zero when every franc is accounted for.
     */
    public function platformDifference(): int
    {
        $credited = (int) Payment::query()->withoutGlobalScopes()->where('payment_status', '!=', Payment::STATUS_REVERSED)->sum('gross_amount');
        $balances = (int) Wallet::query()->withoutGlobalScopes()->sum(\Illuminate\Support\Facades\DB::raw('pending_balance + available_balance'));
        $accrued = (int) FeeAccrual::query()->whereNull('swept_at')->whereNull('cancelled_at')->sum('amount');

        return $credited - $balances - $accrued - $this->moneyOut();
    }

    /** Money debited for cash-outs that were not given back (sent, being sent or unclear). */
    protected function moneyOut(): int
    {
        return (int) \Modules\InjongePay\Models\Cashout::query()->withoutGlobalScopes()->where('status', '!=', \Modules\InjongePay\Models\Cashout::FAILED)->sum('amount');
    }
}
