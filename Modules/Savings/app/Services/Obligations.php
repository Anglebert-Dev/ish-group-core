<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Line;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\ObligationItem;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * Money owed to us (receivable), by us (payable), or to put back into an
 * account (restore). Outstanding = what the ledger lines and the items say;
 * nothing is stored as a running total.
 */
class Obligations
{
    public function __construct(protected Operations $operations)
    {
    }

    public function create(array $data, ?int $opening = null, ?string $note = null): Obligation
    {
        return DB::transaction(function () use ($data, $opening, $note) {
            $obligation = new Obligation($data);
            $obligation->status = 'open';
            $obligation->save();

            if ($opening) {
                $this->item($obligation, 'basis', $opening, null, $note ?? __('savings::savings.opening_amount'));
            }

            return $obligation;
        });
    }

    public function outstanding(Obligation $obligation): int
    {
        $lines = (int) Line::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN nature IN ('receivable_up','payable_up') THEN amount ELSE -amount END), 0) as total")
            ->value('total');

        return $lines + (int) ObligationItem::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)->sum('amount');
    }

    /**
     * Total that was owed in the first place (opening amounts, advances, restore basis), net of reversals.
     */
    public function original(Obligation $obligation): int
    {
        $lines = (int) Line::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)
            ->whereIn('nature', ['receivable_up', 'payable_up'])->sum('amount');

        return $lines + (int) ObligationItem::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)
            ->where(fn ($q) => $q->where('type', 'basis')->orWhere(fn ($r) => $r->where('type', 'reversal')->where('amount', '<', 0)))->sum('amount');
    }

    /**
     * expected | partly_repaid | settled | overdue | disputed | written_off
     */
    public function state(Obligation $obligation): string
    {
        if (in_array($obligation->status, ['disputed', 'written_off'], true)) {
            return $obligation->status;
        }

        $outstanding = $this->outstanding($obligation);
        $original = $this->original($obligation);

        return match (true) {
            $original > 0 && $outstanding <= 0 => 'settled',
            $obligation->due_date !== null && $obligation->due_date->lt(now()->startOfDay()) => 'overdue',
            $outstanding < $original => 'partly_repaid',
            default => 'expected',
        };
    }

    /**
     * Restore target: count a transaction (e.g. money moved out of savings) toward what must go back.
     * Each transaction can count only once (Scenario 8).
     */
    public function addBasis(Obligation $obligation, Transaction $tx, int $amount, ?string $note = null): ObligationItem
    {
        $this->assertOpen($obligation);

        if (ObligationItem::query()->where('obligation_id', $obligation->id)->where('type', 'basis')->where('transaction_id', $tx->id)->exists()) {
            throw new LedgerException(__('savings::savings.basis_twice'));
        }

        if ($amount <= 0 || $amount > $tx->amount || $tx->isReversed()) {
            throw new LedgerException(__('savings::savings.basis_invalid'));
        }

        return $this->item($obligation, 'basis', $amount, $tx, $note);
    }

    /**
     * Put money back: a transfer into the restore account, which lowers the target.
     */
    public function restore(Obligation $obligation, Account $from, int $amount, array $meta): Transaction
    {
        $this->assertOpen($obligation);

        if ($obligation->direction !== 'restore' || ! $obligation->account) {
            throw new LedgerException(__('savings::savings.restore_invalid'));
        }

        if ($amount > $this->outstanding($obligation)) {
            throw new LedgerException(__('savings::savings.over_outstanding', ['max' => Money::format($this->outstanding($obligation), $obligation->currency)]));
        }

        return DB::transaction(function () use ($obligation, $from, $amount, $meta) {
            $tx = $this->operations->transfer($from, $obligation->account, $amount, $meta + ['description' => __('savings::savings.restore_description', ['name' => $obligation->description])]);
            $this->item($obligation, 'settlement', -$amount, $tx);

            return $tx;
        });
    }

    /**
     * A transfer already recorded counts as putting money back.
     */
    public function linkRestoration(Obligation $obligation, Transaction $tx, int $amount): ObligationItem
    {
        $this->assertOpen($obligation);
        $toAccount = $tx->entries()->where('amount', '>', 0)->value('account_id');

        if ($tx->type !== 'transfer' || $toAccount !== $obligation->account_id || $amount <= 0 || $amount > $this->outstanding($obligation)) {
            throw new LedgerException(__('savings::savings.restore_invalid'));
        }

        if (ObligationItem::query()->where('obligation_id', $obligation->id)->where('type', 'settlement')->where('transaction_id', $tx->id)->exists()) {
            throw new LedgerException(__('savings::savings.basis_twice'));
        }

        return $this->item($obligation, 'settlement', -$amount, $tx);
    }

    public function repay(Obligation $obligation, Account $account, int $amount, array $meta, int $interest = 0, $interestCategory = null): Transaction
    {
        $this->assertOpen($obligation);
        $outstanding = $this->outstanding($obligation);

        if ($amount <= 0 || $amount > $outstanding) {
            throw new LedgerException(__('savings::savings.over_outstanding', ['max' => Money::format($outstanding, $obligation->currency)]));
        }

        return match ($obligation->direction) {
            'receivable' => $this->operations->receiveRepayment($obligation, $account, $amount, $meta),
            'payable' => $this->operations->repayDebt($obligation, $account, $amount, $meta, $interest, $interestCategory),
            default => $this->restore($obligation, $account, $amount, $meta),
        };
    }

    /**
     * Change what is owed (e.g. agreed discount). Reason required.
     */
    public function adjust(Obligation $obligation, int $signedAmount, string $reason): ObligationItem
    {
        if (trim($reason) === '' || $signedAmount === 0) {
            throw new LedgerException(__('savings::savings.reason_required'));
        }

        if ($this->outstanding($obligation) + $signedAmount < 0) {
            throw new LedgerException(__('savings::savings.adjust_below_zero'));
        }

        return $this->item($obligation, 'adjustment', $signedAmount, null, $reason);
    }

    public function writeOff(Obligation $obligation, string $reason): void
    {
        $outstanding = $this->outstanding($obligation);

        DB::transaction(function () use ($obligation, $outstanding, $reason) {
            if ($outstanding > 0) {
                $this->item($obligation, 'write_off', -$outstanding, null, $reason);
            }
            $obligation->forceFill(['status' => 'written_off'])->save();
        });
    }

    public function setDisputed(Obligation $obligation, bool $disputed, ?string $reason = null): void
    {
        if ($obligation->status === 'written_off') {
            throw new LedgerException(__('savings::savings.obligation_closed'));
        }

        $obligation->forceFill(['status' => $disputed ? 'disputed' : 'open'])->save();
        audit(\App\Enums\ActivityLogAction::Updated, __('savings::savings.audit_dispute', ['name' => $obligation->description, 'state' => $disputed ? 'disputed' : 'open', 'reason' => $reason]), $obligation);
    }

    /**
     * Every event behind the outstanding amount, oldest first (the explanation).
     *
     * @return Collection<int, array{date: string, label: string, amount: int, transaction: ?Transaction}>
     */
    public function history(Obligation $obligation): Collection
    {
        $lines = Line::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)->with('transaction')->get()
            ->map(fn (Line $l) => [
                'date' => $l->effective_date->toDateString(),
                'label' => __('savings::savings.nature_'.$l->nature),
                'amount' => in_array($l->nature, ['receivable_up', 'payable_up'], true) ? $l->amount : -$l->amount,
                'transaction' => $l->transaction,
            ]);
        $items = ObligationItem::query()->withoutGlobalScope('business')->where('obligation_id', $obligation->id)->with('transaction')->get()
            ->map(fn (ObligationItem $i) => [
                'date' => $i->effective_date->toDateString(),
                'label' => __('savings::savings.item_'.$i->type).($i->note ? ': '.$i->note : ''),
                'amount' => $i->amount,
                'transaction' => $i->transaction,
            ]);

        return $lines->concat($items)->sortBy('date')->values();
    }

    private function item(Obligation $obligation, string $type, int $amount, ?Transaction $tx = null, ?string $note = null): ObligationItem
    {
        $item = new ObligationItem;
        $item->forceFill([
            'business_id' => $obligation->business_id, 'owner_id' => $obligation->owner_id, 'obligation_id' => $obligation->id, 'type' => $type, 'amount' => $amount,
            'transaction_id' => $tx?->id, 'note' => $note ? mb_substr($note, 0, 500) : null,
            'effective_date' => $tx?->effective_date ?? now()->toDateString(), 'user_id' => Auth::id(),
        ])->save();

        audit(\App\Enums\ActivityLogAction::Updated, __('savings::savings.audit_obligation_item', [
            'type' => __('savings::savings.item_'.$type), 'amount' => Money::format($amount, $obligation->currency), 'name' => $obligation->description,
        ]), $obligation, ['note' => $note], $obligation->business_id);

        return $item;
    }

    private function assertOpen(Obligation $obligation): void
    {
        if ($obligation->status === 'written_off') {
            throw new LedgerException(__('savings::savings.obligation_closed'));
        }
    }
}
