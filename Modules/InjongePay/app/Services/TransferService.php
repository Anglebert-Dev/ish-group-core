<?php

namespace Modules\InjongePay\Services;

use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Counter;
use Modules\InjongePay\Support\Money;

/**
 * Between a business's own merchant accounts only (a business never sees or
 * reaches another business's). Money stays in the platform's GTBank account,
 * so only the two ledger entries change; both happen or neither.
 */
class TransferService
{
    public function __construct(protected Ledger $ledger, protected Approvals $approvals)
    {
    }

    public function destination(string $merchantCode, Wallet $from): Wallet
    {
        $to = Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('business_id', $from->business_id)->where('merchant_code', trim($merchantCode))->first();

        if (! $to || $to->isInternal() || ! $to->is_enabled) {
            throw new InjongePayException(__('injongepay::injongepay.err_transfer_destination'));
        }

        if ($to->id === $from->id) {
            throw new InjongePayException(__('injongepay::injongepay.err_transfer_same'));
        }

        return $to;
    }

    public function execute(Approval $approval): ?Transfer
    {
        $reference = Counter::reference('TRF');

        if (! $claimed = $this->approvals->claim($approval)) {
            return Transfer::query()->withoutGlobalScopes()->where('approval_id', $approval->id)->first();
        }

        try {
            return DB::transaction(function () use ($claimed, $reference) {
                $from = Wallet::query()->withoutGlobalScopes()->findOrFail($claimed->wallet_id);
                $to = Wallet::query()->withoutGlobalScopes()->findOrFail($claimed->payload['to_wallet_id']);

                if ($from->isFrozen()) {
                    throw new InjongePayException(__('injongepay::injongepay.err_wallet_frozen'));
                }

                if (! $from->is_enabled || ! $to->is_enabled || $to->business_id !== $from->business_id) {
                    throw new InjongePayException(__('injongepay::injongepay.err_transfer_destination'));
                }

                $transfer = Transfer::query()->create([
                    'reference' => $reference, 'business_id' => $from->business_id, 'from_wallet_id' => $from->id, 'to_wallet_id' => $to->id,
                    'amount' => $claimed->amount, 'description' => $claimed->payload['description'] ?? null, 'approval_id' => $claimed->id, 'insert_by' => $claimed->requested_by,
                ]);

                // Always lock in id order so two opposite transfers can't deadlock.
                $legs = [[$from, LedgerEntry::DEBIT, 'transfer_out', 'D'], [$to, LedgerEntry::CREDIT, 'transfer_in', 'C']];
                usort($legs, fn ($a, $b) => $a[0]->id <=> $b[0]->id);
                $entries = [];

                foreach ($legs as [$wallet, $direction, $source, $suffix]) {
                    $entries[$source] = $this->ledger->post($wallet, $direction, LedgerEntry::AVAILABLE, $claimed->amount, $source, 'transfer:'.$transfer->id.':'.$suffix, [
                        'reference' => $reference.$suffix, 'source' => $transfer,
                        'description' => __('injongepay::injongepay.ledger_transfer', ['from' => $from->merchant_code, 'to' => $to->merchant_code]),
                    ]);
                }

                $transfer->forceFill(['from_entry_id' => $entries['transfer_out']->id, 'to_entry_id' => $entries['transfer_in']->id])->save();
                $claimed->forceFill(['subject_type' => $transfer->getMorphClass(), 'subject_id' => $transfer->id])->save();

                audit('injongepay_transfer_executed', __('injongepay::injongepay.audit_transfer', ['amount' => Money::format($claimed->amount), 'from' => $from->label(), 'to' => $to->label()]),
                    $transfer, ['approval' => $claimed->uuid], $from->business_id);

                return $transfer;
            });
        } catch (InjongePayException $e) {
            $this->approvals->fail($claimed, $e->getMessage());

            throw $e;
        }
    }
}
