<?php

namespace Modules\Inventory\Services;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;

/**
 * The only way stock changes. Every change is a row in the movement log
 * and the on-hand table is updated in the same transaction, with the rows
 * locked, so two users can never oversell the same stock.
 *
 * Other modules (sales, production, invoicing…) call record() with their
 * own movement type and the document as $reference:
 *
 *   app(StockService::class)->record(MovementType::Sale, [
 *       ['variant' => $variant, 'store' => $store, 'quantity' => -3],
 *   ], reference: $invoice, referenceLabel: $invoice->number);
 */
class StockService
{
    private const EPSILON = 0.0005;

    /**
     * @param  list<array{variant: Variant, store: Store, quantity: int|float|string, unit_cost?: int|float|string|null, type?: MovementType}>  $lines  signed quantities (+ in, − out)
     * @return Collection<int, Movement>
     *
     * @throws InsufficientStock when any variant/store would go below zero (nothing is saved)
     */
    public function record(
        MovementType $type,
        array $lines,
        ?string $reason = null,
        ?Model $reference = null,
        ?string $referenceLabel = null,
    ): Collection {
        $lines = $this->normalise($lines);

        if ($lines === []) {
            return collect();
        }

        $movements = DB::transaction(function () use ($type, $lines, $reason, $reference, $referenceLabel) {
            $balances = $this->lockBalances($lines);

            // Check the net change per variant/store, so two lines of one item can't oversell.
            $net = [];
            foreach ($lines as $line) {
                $key = $this->key($line['variant'], $line['store']);
                $net[$key] = ($net[$key] ?? 0) + $line['quantity'];
            }

            foreach ($net as $key => $delta) {
                if ($balances[$key]->quantity + $delta < -self::EPSILON) {
                    $line = collect($lines)->first(fn ($l) => $this->key($l['variant'], $l['store']) === $key);

                    throw new InsufficientStock(
                        $line['variant']->displayName(),
                        $line['store']->name,
                        $balances[$key]->quantity,
                        -$delta,
                    );
                }
            }

            $batch = UuidLibrary::generate();
            $running = array_map(fn (StockLevel $level) => $level->quantity, $balances);
            $movements = collect();

            foreach ($lines as $line) {
                $key = $this->key($line['variant'], $line['store']);
                $running[$key] = round($running[$key] + $line['quantity'], 3);

                $movement = new Movement;
                $movement->forceFill([
                    'uuid' => UuidLibrary::generate(),
                    'business_id' => $line['variant']->business_id,
                    'batch' => $batch,
                    'item_id' => $line['variant']->item_id,
                    'variant_id' => $line['variant']->id,
                    'store_id' => $line['store']->id,
                    'type' => $line['type'] ?? $type,
                    'quantity' => $line['quantity'],
                    'balance_after' => $running[$key],
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'reference_label' => $referenceLabel,
                    'user_id' => Auth::id(),
                ])->save();

                $movements->push($movement);
            }

            foreach ($balances as $key => $level) {
                $level->forceFill(['quantity' => $running[$key]])->save();
            }

            return $movements;
        });

        $this->audit($type, $lines, $reason, $reference, $referenceLabel);

        return $movements;
    }

    public function receive(Variant $variant, Store $store, float $quantity, ?string $reason = null, ?float $unitCost = null, MovementType $type = MovementType::Receipt): Collection
    {
        return $this->record($type, [['variant' => $variant, 'store' => $store, 'quantity' => abs($quantity), 'unit_cost' => $unitCost]], $reason);
    }

    public function issue(Variant $variant, Store $store, float $quantity, ?string $reason = null, MovementType $type = MovementType::Issue): Collection
    {
        return $this->record($type, [['variant' => $variant, 'store' => $store, 'quantity' => -abs($quantity)]], $reason);
    }

    /**
     * Stock count correction: set the on-hand to what was counted.
     */
    public function adjustTo(Variant $variant, Store $store, float $counted, string $reason): Collection
    {
        if ($counted < 0) {
            throw new InvalidArgumentException('A counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($variant, $store, $counted, $reason) {
            $current = $this->lockBalances([['variant' => $variant, 'store' => $store]])[$this->key($variant, $store)]->quantity;
            $delta = round($counted - $current, 3);

            return abs($delta) < self::EPSILON
                ? collect()
                : $this->record(MovementType::Adjustment, [['variant' => $variant, 'store' => $store, 'quantity' => $delta]], $reason);
        });
    }

    public function transfer(Variant $variant, Store $from, Store $to, float $quantity, ?string $reason = null): Collection
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('Choose two different stores.');
        }

        $quantity = abs($quantity);

        return $this->record(MovementType::TransferOut, [
            ['variant' => $variant, 'store' => $from, 'quantity' => -$quantity, 'type' => MovementType::TransferOut],
            ['variant' => $variant, 'store' => $to, 'quantity' => $quantity, 'type' => MovementType::TransferIn],
        ], $reason);
    }

    public function onHand(Variant $variant, ?Store $store = null): float
    {
        return (float) StockLevel::query()->withoutGlobalScope('business')
            ->where('variant_id', $variant->id)
            ->when($store, fn ($q) => $q->where('store_id', $store->id))
            ->sum('quantity');
    }

    public function itemOnHand(Item $item, ?Store $store = null): float
    {
        return (float) StockLevel::query()->withoutGlobalScope('business')
            ->whereIn('variant_id', $item->variants()->withTrashed()->select('id'))
            ->when($store, fn ($q) => $q->where('store_id', $store->id))
            ->sum('quantity');
    }

    /**
     * On-hand per store for one variant: [store_id => quantity].
     *
     * @return array<int, float>
     */
    public function byStore(Variant $variant): array
    {
        return StockLevel::query()->withoutGlobalScope('business')
            ->where('variant_id', $variant->id)
            ->pluck('quantity', 'store_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }

    /**
     * @return list<array{variant: Variant, store: Store, quantity: float, unit_cost: ?float, type?: MovementType}>
     */
    private function normalise(array $lines): array
    {
        $normalised = [];

        foreach ($lines as $line) {
            $variant = $line['variant'] ?? null;
            $store = $line['store'] ?? null;

            if (! $variant instanceof Variant || ! $store instanceof Store) {
                throw new InvalidArgumentException('Each stock line needs a variant and a store.');
            }

            if ($variant->business_id !== $store->business_id) {
                throw new InvalidArgumentException('Variant and store belong to different businesses.');
            }

            $quantity = round((float) ($line['quantity'] ?? 0), 3);

            if (abs($quantity) < self::EPSILON) {
                continue;
            }

            $variant->loadMissing('item');
            $normalised[] = [
                'variant' => $variant,
                'store' => $store,
                'quantity' => $quantity,
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? round((float) $line['unit_cost'], 2) : null,
            ] + (isset($line['type']) ? ['type' => $line['type']] : []);
        }

        return $normalised;
    }

    /**
     * Lock (creating when missing) the on-hand rows, always in the same
     * order so concurrent movements can't deadlock.
     *
     * @return array<string, StockLevel>
     */
    private function lockBalances(array $lines): array
    {
        $pairs = [];
        foreach ($lines as $line) {
            $pairs[$this->key($line['variant'], $line['store'])] = [$line['variant'], $line['store']];
        }
        ksort($pairs);

        $levels = [];
        foreach ($pairs as $key => [$variant, $store]) {
            StockLevel::query()->withoutGlobalScope('business')->insertOrIgnore([
                'business_id' => $variant->business_id,
                'variant_id' => $variant->id,
                'store_id' => $store->id,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $levels[$key] = StockLevel::query()->withoutGlobalScope('business')
                ->where('variant_id', $variant->id)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $levels;
    }

    private function key(Variant $variant, Store $store): string
    {
        return sprintf('%012d:%012d', $variant->id, $store->id);
    }

    private function audit(MovementType $type, array $lines, ?string $reason, ?Model $reference, ?string $referenceLabel): void
    {
        $first = $lines[0];

        $description = count($lines) === 1 || $type === MovementType::TransferOut
            ? __('inventory::inventory.audit_movement', [
                'type' => $type === MovementType::TransferOut ? __('inventory::inventory.transfer') : $type->label(),
                'quantity' => InsufficientStock::format(abs($first['quantity'])),
                'unit' => $first['variant']->item?->unit,
                'item' => $first['variant']->displayName(),
                'store' => $type === MovementType::TransferOut && isset($lines[1])
                    ? $first['store']->name.' → '.$lines[1]['store']->name
                    : $first['store']->name,
            ])
            : __('inventory::inventory.audit_movements', ['type' => $type->label(), 'count' => count($lines)]);

        if ($referenceLabel) {
            $description .= ' ('.$referenceLabel.')';
        }

        audit(
            'stock_'.$type->value,
            $description,
            $reference ?? $first['variant']->item,
            [
                'reason' => $reason,
                'lines' => collect($lines)->map(fn ($l) => [
                    'item' => $l['variant']->displayName(),
                    'store' => $l['store']->name,
                    'quantity' => $l['quantity'],
                ])->all(),
            ],
            $first['variant']->business_id,
        );
    }
}
