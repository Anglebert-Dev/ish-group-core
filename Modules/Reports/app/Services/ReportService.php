<?php

namespace Modules\Reports\Services;

use App\Libraries\ListExport;
use App\Services\Module\ModuleManager;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\StockLevel;

/**
 * Every report reads history (movements, invoices, entries), so a report
 * for last month stays the same whatever happens today.
 */
class ReportService
{
    /**
     * Report key => [module it needs, permission].
     */
    public const REPORTS = [
        'sales_products' => ['Invoicing', 'list invoicing.invoice'],
        'sales_customers' => ['Invoicing', 'list invoicing.invoice'],
        'production' => ['Production', 'list production.work_order'],
        'stock_value' => ['Inventory', 'list inventory.item'],
        'movements' => ['Inventory', 'list inventory.movement'],
        'purchases' => ['Purchasing', 'list purchasing.order'],
    ];

    public function __construct(protected ModuleManager $modules, protected CurrentBusiness $business)
    {
    }

    /**
     * Reports this user can see in this business.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $business = $this->business->get();

        return array_keys(array_filter(self::REPORTS, fn ($needs) => $this->modules->isActive($needs[0])
            && (! $business || $business->canUseModule($needs[0]))
            && \App\Helpers\AuthHelper::can($needs[1])));
    }

    /**
     * @return array{table: ListExport, totals: array<string, string>}
     */
    public function run(string $report, string $from, string $to): array
    {
        return match ($report) {
            'sales_products' => $this->salesByProduct($from, $to),
            'sales_customers' => $this->salesByCustomer($from, $to),
            'production' => $this->production($from, $to),
            'stock_value' => $this->stockValue(),
            'movements' => $this->movements($from, $to),
            'purchases' => $this->purchases($from, $to),
        };
    }

    private function invoices(string $from, string $to)
    {
        return DB::table('sales_invoices')
            ->where('sales_invoices.business_id', $this->business->id())
            ->where('sales_invoices.status', '!=', 'cancelled')
            ->whereNull('sales_invoices.deleted_at')
            ->whereDate('sales_invoices.invoice_date', '>=', $from)
            ->whereDate('sales_invoices.invoice_date', '<=', $to);
    }

    private function salesByProduct(string $from, string $to): array
    {
        $rows = $this->invoices($from, $to)
            ->join('sales_invoice_lines as l', 'l.invoice_id', '=', 'sales_invoices.id')
            ->join('inventory_variants as v', 'v.id', '=', 'l.variant_id')
            ->join('inventory_items as i', 'i.id', '=', 'v.item_id')
            ->groupBy('i.name', 'v.option1', 'v.option2', 'i.unit')
            ->selectRaw('i.name, v.option1, v.option2, i.unit, SUM(l.quantity) as qty, SUM(l.line_total) as revenue, SUM(l.quantity * COALESCE(v.cost_price, 0)) as cost')
            ->orderByDesc('revenue')
            ->get();

        $totals = $this->invoices($from, $to)->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(tax), 0) as tax, COALESCE(SUM(paid), 0) as paid')->first();

        return [
            'table' => ListExport::make(__('reports::reports.sales_products'), 'sales-by-product', [
                __('reports::reports.product'), __('reports::reports.quantity'), __('reports::reports.revenue'), __('reports::reports.cost'), __('reports::reports.margin'),
            ], $rows->map(fn ($r) => [
                $r->name.(trim($r->option1.' '.$r->option2) !== '' ? ' ('.trim(implode(' / ', array_filter([$r->option1, $r->option2]))).')' : ''),
                InsufficientStock::format((float) $r->qty).' '.$r->unit,
                money($r->revenue), money($r->cost), money((float) $r->revenue - (float) $r->cost),
            ])->all()),
            'totals' => [
                __('reports::reports.invoices') => (string) $totals->n,
                __('reports::reports.sales_total') => money($totals->total),
                __('reports::reports.tax') => money($totals->tax),
                __('reports::reports.paid') => money($totals->paid),
                __('reports::reports.outstanding') => money((float) $totals->total - (float) $totals->paid),
            ],
        ];
    }

    private function salesByCustomer(string $from, string $to): array
    {
        $rows = $this->invoices($from, $to)
            ->join('customers as c', 'c.id', '=', 'sales_invoices.customer_id')
            ->groupBy('c.id', 'c.name', 'c.company_name', 'c.type')
            ->selectRaw('c.name, c.company_name, c.type, COUNT(*) as n, SUM(sales_invoices.total) as total, SUM(sales_invoices.paid) as paid')
            ->orderByDesc('total')
            ->get();

        return [
            'table' => ListExport::make(__('reports::reports.sales_customers'), 'sales-by-customer', [
                __('reports::reports.customer'), __('reports::reports.invoices'), __('reports::reports.sales_total'), __('reports::reports.paid'), __('reports::reports.outstanding'),
            ], $rows->map(fn ($r) => [
                $r->type === 'company' && $r->company_name ? $r->company_name : $r->name,
                (string) $r->n, money($r->total), money($r->paid), money((float) $r->total - (float) $r->paid),
            ])->all()),
            'totals' => [__('reports::reports.customers') => (string) $rows->count(), __('reports::reports.sales_total') => money($rows->sum('total'))],
        ];
    }

    private function production(string $from, string $to): array
    {
        $entries = DB::table('production_entries as e')
            ->join('production_work_orders as w', 'w.id', '=', 'e.work_order_id')
            ->where('e.business_id', $this->business->id())
            ->whereDate('e.work_date', '>=', $from)
            ->whereDate('e.work_date', '<=', $to)
            ->get(['e.work_date', 'e.line', 'e.stage', 'e.kind', 'e.quantity', 'w.stages']);

        // Finished = entries at each work order's last stage; rejects counted apart.
        $rows = $entries->groupBy(fn ($e) => substr((string) $e->work_date, 0, 10).'|'.($e->line ?: '—'))
            ->map(function ($group, $key) {
                [$date, $line] = explode('|', $key);
                $finished = $group->filter(fn ($e) => $e->kind === 'count' && $e->stage === count(json_decode($e->stages, true)) - 1)->sum('quantity');
                $rejected = $group->where('kind', 'reject')->sum('quantity');

                return ['date' => $date, 'line' => $line, 'finished' => (float) $finished, 'rejected' => (float) $rejected];
            })
            ->sortKeys()->values();

        return [
            'table' => ListExport::make(__('reports::reports.production'), 'production', [
                __('reports::reports.date'), __('reports::reports.line'), __('reports::reports.finished'), __('reports::reports.rejected'),
            ], $rows->map(fn ($r) => [$r['date'], $r['line'], InsufficientStock::format($r['finished']), InsufficientStock::format($r['rejected'])])->all()),
            'totals' => [
                __('reports::reports.finished') => InsufficientStock::format($rows->sum('finished')),
                __('reports::reports.rejected') => InsufficientStock::format($rows->sum('rejected')),
                __('reports::reports.reject_rate') => $rows->sum('finished') + $rows->sum('rejected') > 0
                    ? round($rows->sum('rejected') / ($rows->sum('finished') + $rows->sum('rejected')) * 100, 1).'%' : '—',
            ],
        ];
    }

    private function stockValue(): array
    {
        $rows = StockLevel::query()
            ->join('inventory_variants as v', 'v.id', '=', 'inventory_stock.variant_id')
            ->join('inventory_items as i', 'i.id', '=', 'v.item_id')
            ->join('inventory_stores as s', 's.id', '=', 'inventory_stock.store_id')
            ->where('inventory_stock.quantity', '!=', 0)
            ->orderBy('i.name')
            ->get(['i.name', 'i.type', 'i.unit', 'v.option1', 'v.option2', 'v.cost_price', 's.name as store', 'inventory_stock.quantity']);

        $value = fn ($r) => (float) $r->quantity * (float) $r->cost_price;

        return [
            'table' => ListExport::make(__('reports::reports.stock_value'), 'stock-value', [
                __('reports::reports.product'), __('reports::reports.store'), __('reports::reports.quantity'), __('reports::reports.unit_cost'), __('reports::reports.value'),
            ], $rows->map(fn ($r) => [
                $r->name.(trim($r->option1.' '.$r->option2) !== '' ? ' ('.trim(implode(' / ', array_filter([$r->option1, $r->option2]))).')' : ''),
                $r->store, InsufficientStock::format((float) $r->quantity).' '.$r->unit,
                $r->cost_price !== null ? money($r->cost_price) : '—', money($value($r)),
            ])->all()),
            'totals' => [
                __('reports::reports.value_finished') => money($rows->where('type', 'finished')->sum($value)),
                __('reports::reports.value_materials') => money($rows->where('type', 'material')->sum($value)),
                __('reports::reports.value_total') => money($rows->sum($value)),
            ],
        ];
    }

    private function movements(string $from, string $to): array
    {
        $rows = Movement::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('type, COUNT(*) as n, SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as qty_in, SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) as qty_out')
            ->groupBy('type')->get();

        return [
            'table' => ListExport::make(__('reports::reports.movements'), 'stock-movements', [
                __('reports::reports.type'), __('reports::reports.count'), __('reports::reports.in'), __('reports::reports.out'),
            ], $rows->map(fn ($r) => [
                MovementType::tryFrom($r->type instanceof MovementType ? $r->type->value : (string) $r->type)?->label() ?? (string) $r->type,
                (string) $r->n, InsufficientStock::format((float) $r->qty_in), InsufficientStock::format((float) $r->qty_out),
            ])->all()),
            'totals' => [
                __('reports::reports.in') => InsufficientStock::format((float) $rows->sum('qty_in')),
                __('reports::reports.out') => InsufficientStock::format((float) $rows->sum('qty_out')),
            ],
        ];
    }

    private function purchases(string $from, string $to): array
    {
        $rows = DB::table('purchase_receipts as r')
            ->join('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->where('r.business_id', $this->business->id())
            ->whereNull('r.deleted_at')
            ->whereDate('r.received_on', '>=', $from)
            ->whereDate('r.received_on', '<=', $to)
            ->groupBy('s.name')
            ->selectRaw('s.name, COUNT(*) as n, SUM(r.value) as value')
            ->orderByDesc('value')->get();

        return [
            'table' => ListExport::make(__('reports::reports.purchases'), 'purchases', [
                __('reports::reports.supplier'), __('reports::reports.receipts'), __('reports::reports.value'),
            ], $rows->map(fn ($r) => [$r->name, (string) $r->n, money($r->value)])->all()),
            'totals' => [__('reports::reports.value_total') => money($rows->sum('value'))],
        ];
    }
}
