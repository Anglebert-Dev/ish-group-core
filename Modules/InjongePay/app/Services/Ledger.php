<?php

namespace Modules\InjongePay\Services;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Wallet;

/**
 * The only writer of wallet balances. Each entry moves one bucket (pending or
 * available) of one wallet, under a lock on that wallet, and records the
 * balances after it. Posting the same idempotency key twice returns the first
 * entry; neither bucket can go below zero.
 */
class Ledger
{
    /**
     * @param  array{reference?: string, description?: string, counterparty_name?: ?string, counterparty_phone?: ?string, source?: ?Model}  $meta
     */
    public function post(Wallet $wallet, string $direction, string $bucket, int $amount, string $source, string $idempotencyKey, array $meta = []): LedgerEntry
    {
        if ($amount <= 0) {
            throw new InjongePayException(__('injongepay::injongepay.err_amount_positive'));
        }

        if (! in_array($direction, [LedgerEntry::CREDIT, LedgerEntry::DEBIT], true) || ! in_array($bucket, [LedgerEntry::PENDING, LedgerEntry::AVAILABLE], true)) {
            throw new InjongePayException(__('injongepay::injongepay.err_ledger_invalid'));
        }

        return DB::transaction(function () use ($wallet, $direction, $bucket, $amount, $source, $idempotencyKey, $meta) {
            $locked = Wallet::query()->withoutGlobalScopes()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            // Checked under the wallet lock, so two identical posts can't both pass.
            $existing = LedgerEntry::query()->withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $signed = $direction === LedgerEntry::CREDIT ? $amount : -$amount;
            $pending = $locked->pending_balance + ($bucket === LedgerEntry::PENDING ? $signed : 0);
            $available = $locked->available_balance + ($bucket === LedgerEntry::AVAILABLE ? $signed : 0);

            if ($pending < 0 || $available < 0) {
                throw new InjongePayException(__('injongepay::injongepay.err_insufficient_balance'));
            }

            $actor = auth()->user();
            $model = $meta['source'] ?? null;

            $entry = LedgerEntry::query()->create([
                'business_id' => $locked->business_id,
                'wallet_id' => $locked->id,
                'reference' => $meta['reference'] ?? 'LED'.Str::upper((string) Str::ulid()),
                'idempotency_key' => $idempotencyKey,
                'direction' => $direction,
                'bucket' => $bucket,
                'amount' => $amount,
                'pending_after' => $pending,
                'available_after' => $available,
                'source' => $source,
                'source_type' => $model ? $model->getMorphClass() : null,
                'source_id' => $model?->getKey(),
                'description' => $meta['description'] ?? null,
                'counterparty_name' => $meta['counterparty_name'] ?? null,
                'counterparty_phone' => $meta['counterparty_phone'] ?? null,
                'actor_id' => $actor?->getKey(),
                'actor_name' => $actor?->name ?? __('injongepay::injongepay.actor_system'),
            ]);

            // The entry is the record of this change; no separate audit row per balance update.
            ActivityLogService::muted(fn () => $locked->forceFill(['pending_balance' => $pending, 'available_balance' => $available])->save());

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $entry;
        });
    }

    /**
     * Moves money from pending to available (bank confirmed it): two entries, one transaction.
     *
     * @return array{0: LedgerEntry, 1: LedgerEntry}
     */
    public function clear(Wallet $wallet, int $amount, string $source, string $idempotencyKey, array $meta = []): array
    {
        $leg = fn (string $suffix) => isset($meta['reference']) ? array_merge($meta, ['reference' => $meta['reference'].$suffix]) : $meta;

        return DB::transaction(fn () => [
            $this->post($wallet, LedgerEntry::DEBIT, LedgerEntry::PENDING, $amount, $source, $idempotencyKey.':out', $leg('P')),
            $this->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, $amount, $source, $idempotencyKey.':in', $leg('A')),
        ]);
    }

    /**
     * Recomputes a wallet's balances from its entries (the reconciliation check).
     *
     * @return array{pending: int, available: int}
     */
    public function recompute(Wallet $wallet): array
    {
        $sum = fn (string $bucket) => (int) LedgerEntry::query()->withoutGlobalScopes()->where('wallet_id', $wallet->id)->where('bucket', $bucket)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total")->value('total');

        return ['pending' => $sum(LedgerEntry::PENDING), 'available' => $sum(LedgerEntry::AVAILABLE)];
    }
}
