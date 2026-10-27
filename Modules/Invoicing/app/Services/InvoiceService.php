<?php

namespace Modules\Invoicing\Services;

use App\Enums\ActivityLogAction;
use App\Libraries\ListExport;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use App\Support\Documents\DocumentNumber;
use App\Support\Settings\BusinessSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Services\StockService;
use Modules\Invoicing\Enums\InvoiceStatus;
use Modules\Invoicing\Enums\PaymentMethod;
use Modules\Invoicing\Models\SalesInvoice;
use Modules\Invoicing\Models\SalesInvoiceLine;
use Modules\Invoicing\Models\SalesPayment;
use RuntimeException;

class InvoiceService extends BaseService
{
    public const FILTERS = ['q', 'status', 'customer', 'from', 'to', 'overdue'];

    public function __construct(
        protected DocumentNumber $numbers,
        protected StockService $stock,
        protected BusinessSettings $settings,
        protected ModuleManager $modules,
    ) {
    }

    public function taxRate(): float
    {
        return (float) $this->settings->get('invoicing.tax_rate', 0);
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)->with('customer')->latest('invoice_date')->latest('id')->paginate($perPage)->withQueryString();
    }

    public function exportList(array $filters): ListExport
    {
        $rows = $this->filtered($filters)->with('customer')->latest('invoice_date')->limit(5000)->get()->map(fn (SalesInvoice $i) => [
            $i->number,
            $i->customer?->displayName() ?? '—',
            $i->invoice_date->format('Y-m-d'),
            $i->due_date?->format('Y-m-d') ?? '—',
            $i->status->label(),
            number_format((float) $i->total, 2),
            number_format((float) $i->paid, 2),
            number_format($i->balance(), 2),
        ])->all();

        return ListExport::make(__('invoicing::invoicing.invoices'), 'invoices', [
            __('invoicing::invoicing.number'), __('invoicing::invoicing.customer'), __('invoicing::invoicing.invoice_date'),
            __('invoicing::invoicing.due_date'), __('body.status'), __('inventory::inventory.total'),
            __('invoicing::invoicing.paid'), __('invoicing::invoicing.balance'),
        ], $rows);
    }

    public function filtered(array $filters): Builder
    {
        return SalesInvoice::query()
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $query, string $q) {
                $like = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(number) LIKE ?', [$like])
                    ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(company_name) LIKE ?', [$like])));
            })
            ->when(InvoiceStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value]))
            ->when($filters['customer'] ?? null, fn ($q, $uuid) => $q->whereHas('customer', fn ($c) => $c->where('uuid', $uuid)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('invoice_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('invoice_date', '<=', $to))
            ->when(! empty($filters['overdue']), fn ($q) => $q->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value])
                ->whereDate('due_date', '<', now()->toDateString()));
    }

    /**
     * Issue an invoice. Goods leave stock with it (checked across all lines)
     * unless they already left with a delivered sales order.
     *
     * @param  list<array{variant: \Modules\Inventory\Models\Variant, description: ?string, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    public function create(array $data, array $lines): array
    {
        $salesOrder = $this->salesOrder($data['sales_order_id'] ?? null);

        if ($salesOrder && SalesInvoice::query()->where('sales_order_id', $salesOrder->id)->where('status', '!=', InvoiceStatus::Cancelled->value)->exists()) {
            return $this->failure(__('invoicing::invoicing.order_already_invoiced', ['number' => $salesOrder->number]));
        }

        try {
            $invoice = DB::transaction(function () use ($data, $lines, $salesOrder) {
                // An order not yet delivered is delivered now (that takes the stock out once).
                if ($salesOrder && $salesOrder->status->reservesStock()) {
                    $result = app(\Modules\SalesOrders\Services\OrderService::class)->deliver($salesOrder);

                    if (! $result['status']) {
                        throw new RuntimeException($result['message']);
                    }
                }

                $invoice = new SalesInvoice($data);
                $invoice->number = $this->numbers->next('invoice', (string) config('invoicing.prefix', 'INV'));
                $invoice->status = InvoiceStatus::Unpaid;
                $invoice->tax_rate = $this->taxRate();
                $invoice->stock_issued = $salesOrder === null;
                $invoice->save();

                $this->writeLines($invoice, $lines);

                if ($invoice->stock_issued) {
                    $invoice->loadMissing('store');
                    $this->stock->record(MovementType::Sale, array_map(fn ($line) => [
                        'variant' => $line['variant'], 'store' => $invoice->store, 'quantity' => -$line['quantity'],
                    ], $lines), __('invoicing::invoicing.sale_reason', ['customer' => $invoice->customer?->displayName()]), $invoice, $invoice->number);
                }

                return $invoice;
            });
        } catch (InsufficientStock|RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($invoice, __('invoicing::invoicing.created', ['number' => $invoice->number]));
    }

    /**
     * Lines copied from a sales order, for a new invoice.
     */
    public function linesFromOrder(object $salesOrder): array
    {
        return $salesOrder->lines->map(fn ($line) => [
            'variant' => $line->variant?->uuid,
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
        ])->all();
    }

    /**
     * Money received. Never more than the balance (no overpayment).
     */
    public function addPayment(SalesInvoice $invoice, array $data): array
    {
        $amount = round((float) $data['amount'], 2);

        try {
            $payment = DB::transaction(function () use ($invoice, $data, $amount) {
                /** @var SalesInvoice $locked */
                $locked = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === InvoiceStatus::Cancelled) {
                    throw new RuntimeException(__('invoicing::invoicing.cancelled_no_payment'));
                }

                if ($amount <= 0) {
                    throw new RuntimeException(__('invoicing::invoicing.amount_positive'));
                }

                if ($amount > $locked->balance() + 0.001) {
                    throw new RuntimeException(__('invoicing::invoicing.overpayment', ['balance' => money($locked->balance())]));
                }

                $payment = new SalesPayment([
                    'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                    'amount' => $amount,
                    'method' => PaymentMethod::from($data['method']),
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
                $payment->business_id = $locked->business_id;
                $payment->invoice_id = $locked->id;
                $payment->customer_id = $locked->customer_id;
                $payment->number = $this->numbers->next('receipt', (string) config('invoicing.receipt_prefix', 'RC'));
                $payment->save();

                $this->refreshPaid($locked);

                return $payment;
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($payment, __('invoicing::invoicing.payment_recorded', ['number' => $payment->number]));
    }

    /**
     * Undo a payment entered by mistake. It stays in the list, marked reversed.
     */
    public function reversePayment(SalesPayment $payment, string $reason): array
    {
        if ($payment->isReversed()) {
            return $this->failure(__('invoicing::invoicing.already_reversed'));
        }

        DB::transaction(function () use ($payment, $reason) {
            $payment->forceFill(['reversed_at' => now(), 'reverse_reason' => mb_substr($reason, 0, 500)])->save();
            $this->refreshPaid(SalesInvoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail());
        });

        audit(ActivityLogAction::Updated, __('invoicing::invoicing.audit_reversed', ['number' => $payment->number, 'reason' => $reason]), $payment);

        return $this->success($payment, __('invoicing::invoicing.payment_reversed'));
    }

    /**
     * Cancel with a credit note. Payments must be reversed first; goods can go back to stock.
     */
    public function cancel(SalesInvoice $invoice, string $reason, bool $returnStock): array
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $this->failure(__('invoicing::invoicing.already_cancelled'));
        }

        if ((float) $invoice->paid > 0) {
            return $this->failure(__('invoicing::invoicing.cancel_has_payments'));
        }

        DB::transaction(function () use ($invoice, $reason, $returnStock) {
            $invoice->loadMissing(['lines.variant', 'store']);

            if ($returnStock) {
                $this->stock->record(MovementType::SaleReturn, $invoice->lines->map(fn (SalesInvoiceLine $line) => [
                    'variant' => $line->variant, 'store' => $invoice->store, 'quantity' => $line->quantity,
                ])->all(), __('invoicing::invoicing.return_reason', ['number' => $invoice->number]), $invoice, $invoice->number);
            }

            $from = $invoice->status;
            $invoice->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'credit_note_number' => $this->numbers->next('credit_note', (string) config('invoicing.credit_note_prefix', 'CN')),
                'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 500),
                'stock_returned' => $returnStock,
            ])->save();

            audit(ActivityLogAction::Updated, __('invoicing::invoicing.audit_cancelled', [
                'number' => $invoice->number, 'credit' => $invoice->credit_note_number, 'reason' => $reason,
            ]), $invoice, ['from' => $from->value, 'stock_returned' => $returnStock]);
        });

        return $this->success($invoice, __('invoicing::invoicing.cancelled', ['credit' => $invoice->credit_note_number]));
    }

    /**
     * What a customer owes across open invoices.
     */
    public function customerBalance(int $customerId): float
    {
        return (float) SalesInvoice::query()->where('customer_id', $customerId)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value])
            ->selectRaw('COALESCE(SUM(total - paid), 0) as owed')->value('owed');
    }

    private function writeLines(SalesInvoice $invoice, array $lines): void
    {
        foreach (array_values($lines) as $position => $line) {
            $row = new SalesInvoiceLine([
                'variant_id' => $line['variant']->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
            $row->business_id = $invoice->business_id;
            $row->invoice_id = $invoice->id;
            $row->save();
        }

        $subtotal = (float) $invoice->lines()->sum('line_total');
        $discount = min((float) $invoice->discount, $subtotal);
        $tax = round(($subtotal - $discount) * $invoice->tax_rate / 100, 2);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => round($subtotal - $discount + $tax, 2),
        ])->save();
    }

    private function refreshPaid(SalesInvoice $invoice): void
    {
        $paid = round((float) SalesPayment::query()->where('invoice_id', $invoice->id)->whereNull('reversed_at')->sum('amount'), 2);

        $invoice->forceFill([
            'paid' => $paid,
            'status' => match (true) {
                $paid <= 0 => InvoiceStatus::Unpaid,
                $paid + 0.001 >= (float) $invoice->total => InvoiceStatus::Paid,
                default => InvoiceStatus::PartiallyPaid,
            },
        ])->save();
    }

    private function salesOrder(?int $id): ?object
    {
        if (! $id || ! $this->modules->isActive('SalesOrders')) {
            return null;
        }

        return \Modules\SalesOrders\Models\SalesOrder::query()->find($id);
    }
}
