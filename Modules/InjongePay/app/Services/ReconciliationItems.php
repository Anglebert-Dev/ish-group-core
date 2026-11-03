<?php

namespace Modules\InjongePay\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformAlert;

/**
 * A difference with GTBank opens one item (the same difference never opens
 * two) and tells the platform. It stays open until someone explains it.
 */
class ReconciliationItems
{
    public function open(string $kind, string $key, ?Wallet $wallet, ?Model $subject, ?int $amount, array $details = []): ReconciliationItem
    {
        $item = ReconciliationItem::query()->firstOrCreate(['item_key' => $kind.':'.$key], [
            'kind' => $kind,
            'business_id' => $wallet?->business_id,
            'wallet_id' => $wallet?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'amount' => $amount,
            'details' => $details,
        ]);

        if ($item->wasRecentlyCreated) {
            $text = __('injongepay::injongepay.item_'.$kind).($wallet ? ' · '.$wallet->label() : '').($amount !== null ? ' · '.Money::format($amount) : '');
            audit('injongepay_reconciliation_item', $text, $item, ['kind' => $kind] + $details, $wallet?->business_id);
            PlatformAlert::send('list injongepay_platform.reconciliation', __('injongepay::injongepay.notify_item_title'), $text, route('injongepay.platform.reconciliation.index', [], false));
        }

        return $item;
    }

    public function resolve(ReconciliationItem $item, string $note): void
    {
        $item->forceFill([
            'status' => ReconciliationItem::RESOLVED, 'resolved_by' => auth()->id(), 'resolved_by_name' => auth()->user()?->name,
            'resolved_at' => now(), 'resolution_note' => $note,
            // Free the key so the same difference, if it comes back, opens a new item.
            'item_key' => $item->item_key.':resolved:'.now()->format('YmdHis'),
        ])->save();

        audit('injongepay_reconciliation_resolved', __('injongepay::injongepay.audit_item_resolved', ['kind' => __('injongepay::injongepay.item_'.$item->kind)]), $item, ['note' => $note], $item->business_id);
    }
}
