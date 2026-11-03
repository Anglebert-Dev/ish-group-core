<?php

namespace Modules\InjongePay\Services;

use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * Credits accrued fee shares to the platform's wallets: one ledger entry per
 * wallet per run, for payments GTBank has confirmed and not reversed.
 */
class FeeSweep
{
    public function __construct(protected Ledger $ledger)
    {
    }

    /** @return int total credited, in cents */
    public function run(): int
    {
        $total = 0;

        foreach (FeeAccrual::query()->whereNull('swept_at')->whereNull('cancelled_at')->distinct()->pluck('wallet_id') as $walletId) {
            $total += DB::transaction(function () use ($walletId) {
                $accruals = FeeAccrual::query()->where('wallet_id', $walletId)->whereNull('swept_at')->whereNull('cancelled_at')
                    ->whereIn('payment_id', Payment::query()->withoutGlobalScopes()->whereNotNull('cleared_at')->where('payment_status', '!=', Payment::STATUS_REVERSED)->select('id'))
                    ->lockForUpdate()->orderBy('id')->get();

                if ($accruals->isEmpty()) {
                    return 0;
                }

                $amount = (int) $accruals->sum('amount');
                $wallet = Wallet::query()->withoutGlobalScopes()->findOrFail($walletId);
                $entry = $this->ledger->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, $amount, 'fee_sweep', 'fee_sweep:'.$walletId.':'.$accruals->last()->id, [
                    'description' => __('injongepay::injongepay.ledger_fee_sweep', ['count' => $accruals->count()]),
                ]);

                FeeAccrual::query()->whereIn('id', $accruals->pluck('id'))->update(['swept_at' => now(), 'ledger_entry_id' => $entry->id]);

                audit('injongepay_fees_swept', __('injongepay::injongepay.audit_fees_swept', ['amount' => Money::format($amount), 'wallet' => $wallet->name, 'count' => $accruals->count()]),
                    $wallet, ['amount' => $amount, 'accruals' => $accruals->count(), 'entry' => $entry->uuid], $wallet->business_id);

                return $amount;
            });
        }

        return $total;
    }
}
