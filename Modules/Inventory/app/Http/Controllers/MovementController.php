<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExport;
use App\Libraries\ListExportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Services\StoreService;
use Symfony\Component\HttpFoundation\Response;

class MovementController extends Controller
{
    private const FILTERS = ['item', 'store', 'type', 'from', 'to'];

    public function __construct(protected StoreService $stores)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTERS);

        return view('inventory::movements.index', [
            'movements' => $this->filtered($filters)->with(['item', 'variant', 'store', 'user'])->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'stores' => $this->stores->all(),
            'item' => filled($filters['item'] ?? null) ? Item::query()->withTrashed()->where('uuid', $filters['item'])->first() : null,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->export($request->only(self::FILTERS)));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->export($request->only(self::FILTERS)));
    }

    private function export(array $filters): ListExport
    {
        $rows = $this->filtered($filters)->with(['variant.item', 'store', 'user'])->latest('id')->limit(5000)->get()
            ->map(fn (Movement $m) => [
                $m->created_at->format('Y-m-d H:i'),
                $m->variant?->displayName() ?? '—',
                $m->store?->name ?? '—',
                $m->type->label(),
                InsufficientStock::format($m->quantity),
                InsufficientStock::format($m->balance_after),
                $m->reference_label ?: '—',
                $m->reason ?: '—',
                $m->user?->name ?? '—',
            ])->all();

        return ListExport::make(__('inventory::inventory.movements'), 'stock-history', [
            __('body.when'), __('inventory::inventory.item'), __('inventory::inventory.store'), __('body.type'),
            __('inventory::inventory.quantity'), __('inventory::inventory.balance_after'), __('inventory::inventory.reference'),
            __('inventory::inventory.reason'), __('inventory::inventory.by'),
        ], $rows);
    }

    private function filtered(array $filters): Builder
    {
        return Movement::query()
            ->when($filters['item'] ?? null, fn ($q, $uuid) => $q->whereIn('item_id', Item::query()->withTrashed()->where('uuid', $uuid)->select('id')))
            ->when($filters['store'] ?? null, fn ($q, $uuid) => $q->whereHas('store', fn ($s) => $s->where('uuid', $uuid)))
            ->when(MovementType::tryFrom((string) ($filters['type'] ?? '')), fn ($q, $type) => $q->where('type', $type->value))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'));
    }
}
