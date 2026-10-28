<?php

namespace Modules\Inventory\Services;

use App\Libraries\ListExport;
use App\Services\BaseService;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\ItemType;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\Variant;

class ItemService extends BaseService
{
    public const FILTERS = ['q', 'type', 'category', 'stock', 'status'];

    public function __construct(
        protected StockService $stock,
        protected StoreService $stores,
        protected BusinessSettings $settings,
        protected CurrentBusiness $business,
    ) {
    }

    /**
     * @return list<string>
     */
    public function units(): array
    {
        return array_values((array) $this->settings->get('inventory.units', config('inventory.units')));
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return Item::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function exportList(array $filters): ListExport
    {
        $rows = $this->filtered($filters)->orderBy('name')->limit(5000)->get()->map(fn (Item $item) => [
            $item->name,
            $item->sku ?: '—',
            $item->type->label(),
            $item->category ?: '—',
            InsufficientStock::format((float) $item->on_hand),
            $item->unit,
        ])->all();

        return ListExport::make(__('inventory::inventory.items'), 'items', [
            __('body.name'), __('inventory::inventory.sku'), __('body.type'), __('inventory::inventory.category'),
            __('inventory::inventory.on_hand'), __('inventory::inventory.unit'),
        ], $rows);
    }

    /**
     * Items with on_hand (all stores) and low_variants (count at or below reorder level).
     */
    public function filtered(array $filters): Builder
    {
        $onHand = StockLevel::query()->withoutGlobalScope('business')
            ->join('inventory_variants as v', 'v.id', '=', 'inventory_stock.variant_id')
            ->whereColumn('v.item_id', 'inventory_items.id')
            ->whereNull('v.deleted_at')
            ->selectRaw('COALESCE(SUM(inventory_stock.quantity), 0)');

        // A variant is low when its total on hand is at or below its (or the item's) reorder level.
        $low = Variant::query()->withoutGlobalScope('business')
            ->from('inventory_variants as lv')
            ->whereColumn('lv.item_id', 'inventory_items.id')
            ->whereNull('lv.deleted_at')
            ->where('lv.is_active', true)
            ->whereRaw('COALESCE(lv.reorder_level, inventory_items.reorder_level) IS NOT NULL')
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity), 0) FROM inventory_stock s WHERE s.variant_id = lv.id) <= COALESCE(lv.reorder_level, inventory_items.reorder_level)')
            ->selectRaw('COUNT(*)');

        return Item::query()
            ->select('inventory_items.*')
            ->selectSub($onHand, 'on_hand')
            ->selectSub($low, 'low_variants')
            ->withCount(['variants' => fn ($q) => $q->where('is_active', true)])
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $like = '%'.mb_strtolower(trim($q)).'%';
                $query->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(category) LIKE ?', [$like])
                    ->orWhereHas('variants', fn ($v) => $v->whereRaw('LOWER(sku) LIKE ?', [$like])));
            })
            ->when(in_array($filters['type'] ?? null, array_column(ItemType::cases(), 'value'), true), fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when(($filters['status'] ?? 'active') === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['stock'] ?? null) === 'low', fn ($q) => $q->whereRaw('('.$low->toSql().') > 0', $low->getBindings()))
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->whereRaw('('.$onHand->toSql().') <= 0', $onHand->getBindings()));
    }

    /**
     * Active variants for document lines (orders, invoices…), finished goods by default.
     * Purchases pass $costPrices so the suggested price is the cost.
     *
     * @return list<array{uuid: string, label: string, price: ?string, cost: ?string, unit: string}>
     */
    public function lineOptions(?ItemType $type = ItemType::Finished, bool $costPrices = false): array
    {
        return Variant::query()
            ->with('item')
            ->where('is_active', true)
            ->whereHas('item', fn ($q) => $q->where('is_active', true)->when($type, fn ($w) => $w->where('type', $type->value)))
            ->get()
            ->sortBy(fn (Variant $v) => mb_strtolower($v->displayName()))
            ->map(fn (Variant $v) => [
                'uuid' => $v->uuid,
                'label' => $v->displayName().($v->sku ? ' · '.$v->sku : ''),
                'price' => $costPrices ? $v->cost_price : $v->sale_price,
                'cost' => $v->cost_price,
                'unit' => $v->item->unit,
                // For the size × colour grid.
                'item' => $v->item->uuid,
                'item_name' => $v->item->name,
                'option1' => $v->option1,
                'option2' => $v->option2,
                'option1_name' => $v->item->option1_name,
                'option2_name' => $v->item->option2_name,
            ])
            ->values()
            ->all();
    }

    public function lowStockCount(): int
    {
        return $this->filtered(['stock' => 'low'])->count();
    }

    /**
     * @param  list<array{id?: int|string|null, option1?: ?string, option2?: ?string, sku?: ?string, cost_price?: mixed, sale_price?: mixed, reorder_level?: mixed, opening?: mixed}>  $variants
     */
    public function create(array $data, array $variants): array
    {
        $item = DB::transaction(function () use ($data, $variants) {
            $item = new Item($data);
            $item->is_active = true;
            $item->save();

            $opening = [];
            $store = $this->stores->default($this->business->get() ?? $item->business);

            foreach ($this->rows($item, $variants) as $row) {
                $variant = $item->variants()->make($this->variantFields($row));
                $variant->business_id = $item->business_id;
                $variant->is_active = true;
                $variant->save();

                if ((float) ($row['opening'] ?? 0) > 0) {
                    $opening[] = ['variant' => $variant, 'store' => $store, 'quantity' => (float) $row['opening'], 'unit_cost' => $variant->cost_price];
                }
            }

            if ($opening !== []) {
                $this->stock->record(MovementType::Opening, $opening, __('inventory::inventory.opening_reason'));
            }

            return $item;
        });

        return $this->success($item, __('inventory::inventory.item_created'));
    }

    public function update(Item $item, array $data, array $variants): array
    {
        DB::transaction(function () use ($item, $data, $variants) {
            $item->fill($data)->save();

            $keep = [];
            foreach ($this->rows($item, $variants) as $row) {
                $variant = filled($row['id'] ?? null)
                    ? $item->variants()->whereKey($row['id'])->first()
                    : null;

                if (! $variant) {
                    $variant = $item->variants()->make();
                    $variant->business_id = $item->business_id;
                }

                $variant->fill($this->variantFields($row));
                $variant->is_active = true;
                $variant->save();
                $keep[] = $variant->id;
            }

            // Removed rows: delete when never used, otherwise keep the history and deactivate.
            $item->variants()->whereNotIn('id', $keep)->get()->each(function (Variant $variant) {
                Movement::query()->where('variant_id', $variant->id)->exists()
                    ? $variant->forceFill(['is_active' => false])->save()
                    : $variant->delete();
            });
        });

        return $this->success($item, __('inventory::inventory.item_updated'));
    }

    public function setActive(Item $item, bool $active): array
    {
        $item->forceFill(['is_active' => $active])->save();

        return $this->success($item, $active ? __('inventory::inventory.item_activated') : __('inventory::inventory.item_deactivated'));
    }

    /**
     * Only an item that never moved can be deleted; otherwise deactivate it.
     */
    public function delete(Item $item): array
    {
        if (Movement::query()->where('item_id', $item->id)->exists()) {
            return $this->failure(__('inventory::inventory.item_has_history'));
        }

        DB::transaction(function () use ($item) {
            StockLevel::query()->whereIn('variant_id', $item->variants()->select('id'))->delete();
            $item->variants->each->delete();
            $item->delete();
        });

        return $this->success(null, __('inventory::inventory.item_deleted'));
    }

    /**
     * Variant rows from the form; one blank row when the item has no options.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Item $item, array $variants): array
    {
        $rows = collect($variants)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row))
            ->values();

        if (! $item->hasOptions()) {
            $first = $rows->first() ?? [];

            return [['option1' => null, 'option2' => null] + $first];
        }

        return $rows->filter(fn ($row) => filled($row['option1'] ?? null) || filled($row['option2'] ?? null))->values()->all();
    }

    private function variantFields(array $row): array
    {
        $number = fn ($value) => $value === null || $value === '' ? null : $value;

        return [
            'option1' => $row['option1'] ?? null ?: null,
            'option2' => $row['option2'] ?? null ?: null,
            'sku' => $row['sku'] ?? null ?: null,
            'cost_price' => $number($row['cost_price'] ?? null),
            'sale_price' => $number($row['sale_price'] ?? null),
            'reorder_level' => $number($row['reorder_level'] ?? null),
        ];
    }
}
