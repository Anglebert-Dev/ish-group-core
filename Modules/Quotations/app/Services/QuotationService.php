<?php

namespace Modules\Quotations\Services;

use App\Enums\ActivityLogAction;
use App\Services\BaseService;
use App\Services\Module\ModuleManager;
use App\Support\Documents\DocumentNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Services\StockService;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Enums\SampleStatus;
use Modules\Quotations\Models\Quotation;
use Modules\Quotations\Models\QuotationDeposit;
use Modules\Quotations\Models\QuotationLine;
use Modules\SalesOrders\Services\OrderService;

class QuotationService extends BaseService
{
    public const FILTERS = ['q', 'status', 'kind', 'follow_up'];

    public function __construct(
        protected DocumentNumber $numbers,
        protected StockService $stock,
        protected OrderService $orders,
        protected ModuleManager $modules,
    ) {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Quotation::query()
            ->with('customer')
            ->when(trim((string) ($filters['q'] ?? '')), function (Builder $query, string $q) {
                $like = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w->whereRaw('LOWER(number) LIKE ?', [$like])
                    ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(company_name) LIKE ?', [$like])));
            })
            ->when(QuoteStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(($filters['kind'] ?? null) === 'sample', fn ($q) => $q->where('is_sample', true))
            ->when(($filters['kind'] ?? null) === 'quote', fn ($q) => $q->where('is_sample', false))
            ->when(! empty($filters['follow_up']), fn ($q) => $q->where('status', QuoteStatus::Open->value)->whereDate('follow_up_date', '<=', now()->toDateString()))
            ->latest('quote_date')->latest('id')
            ->paginate($perPage)->withQueryString();
    }

    public function create(array $data, array $lines): array
    {
        $quote = DB::transaction(function () use ($data, $lines) {
            $quote = new Quotation($data);
            $quote->number = $this->numbers->next('quotation', (string) config('quotations.prefix', 'QT'));
            $quote->status = QuoteStatus::Open;
            $quote->sample_status = $quote->is_sample ? SampleStatus::Requested : null;
            $quote->save();
            $this->writeLines($quote, $lines);

            return $quote;
        });

        return $this->success($quote, __('quotations::quotations.created', ['number' => $quote->number]));
    }

    public function update(Quotation $quote, array $data, array $lines): array
    {
        if ($quote->status !== QuoteStatus::Open) {
            return $this->failure(__('quotations::quotations.not_open'));
        }

        DB::transaction(function () use ($quote, $data, $lines) {
            unset($data['is_sample']);
            $quote->fill($data)->save();
            $quote->lines()->delete();
            $this->writeLines($quote, $lines);
        });

        return $this->success($quote, __('quotations::quotations.updated'));
    }

    public function setStatus(Quotation $quote, QuoteStatus $status): array
    {
        if ($quote->status === QuoteStatus::Converted || $status === QuoteStatus::Converted) {
            return $this->failure(__('quotations::quotations.not_open'));
        }

        $this->transition($quote, ['status' => $status]);

        return $this->success($quote, __('quotations::quotations.status_saved'));
    }

    /**
     * Samples: Sent takes the sample pieces out of stock; Returned puts them back.
     */
    public function setSampleStatus(Quotation $quote, SampleStatus $status): array
    {
        if (! $quote->is_sample) {
            return $this->failure(__('quotations::quotations.not_sample'));
        }

        $from = $quote->sample_status;

        try {
            DB::transaction(function () use ($quote, $status, $from) {
                $quote->loadMissing(['lines.variant', 'store']);
                $lines = fn (int $sign) => $quote->lines->map(fn (QuotationLine $line) => [
                    'variant' => $line->variant, 'store' => $quote->store, 'quantity' => $sign * $line->quantity,
                ])->all();

                $allowed = match ($from) {
                    SampleStatus::Requested => [SampleStatus::Sent, SampleStatus::Approved, SampleStatus::Rejected],
                    SampleStatus::Sent => [SampleStatus::Approved, SampleStatus::Rejected, SampleStatus::Returned],
                    SampleStatus::Approved, SampleStatus::Rejected => [SampleStatus::Approved, SampleStatus::Rejected, SampleStatus::Returned],
                    default => [],
                };

                if (! in_array($status, $allowed, true) || $status === $from) {
                    throw new \RuntimeException(__('quotations::quotations.sample_cannot'));
                }

                // Out when sent, back when returned (only if it actually went out).
                if ($status === SampleStatus::Sent) {
                    $this->stock->record(MovementType::Issue, $lines(-1), __('quotations::quotations.sample_out', ['number' => $quote->number]), $quote, $quote->number);
                } elseif ($status === SampleStatus::Returned) {
                    $wentOut = \Modules\Inventory\Models\Movement::query()
                        ->where('reference_type', $quote->getMorphClass())->where('reference_id', $quote->id)->where('type', MovementType::Issue->value)->exists();
                    if (! $wentOut) {
                        throw new \RuntimeException(__('quotations::quotations.sample_not_out'));
                    }
                    $this->stock->record(MovementType::Receipt, $lines(1), __('quotations::quotations.sample_back', ['number' => $quote->number]), $quote, $quote->number);
                }

                $this->transition($quote, ['sample_status' => $status]);
            });
        } catch (InsufficientStock|\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($quote, __('quotations::quotations.sample_saved', ['status' => $status->label()]));
    }

    /**
     * New pending sales order with the same customer, products, prices and discount.
     */
    public function convert(Quotation $quote): array
    {
        if (! in_array($quote->status, [QuoteStatus::Open, QuoteStatus::Accepted], true)) {
            return $this->failure(__('quotations::quotations.not_open'));
        }

        $quote->loadMissing('lines.variant');

        $order = DB::transaction(function () use ($quote) {
            $result = $this->orders->create([
                'customer_id' => $quote->customer_id,
                'store_id' => $quote->store_id,
                'order_date' => now()->toDateString(),
                'discount' => $quote->discount,
                'notes' => trim(__('quotations::quotations.order_note', ['number' => $quote->number])."\n".$quote->notes),
            ], $quote->lines->map(fn (QuotationLine $line) => [
                'variant' => $line->variant,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
            ])->all());

            $this->transition($quote, ['status' => QuoteStatus::Converted, 'sales_order_id' => $result['data']->id]);

            return $result['data'];
        });

        return $this->success($order, __('quotations::quotations.converted', ['number' => $order->number]));
    }

    public function addDeposit(Quotation $quote, array $data): array
    {
        if ($quote->status === QuoteStatus::Rejected) {
            return $this->failure(__('quotations::quotations.not_open'));
        }

        $deposit = new QuotationDeposit($data);
        $deposit->business_id = $quote->business_id;
        $deposit->quotation_id = $quote->id;
        $deposit->save();

        return $this->success($deposit, __('quotations::quotations.deposit_saved'));
    }

    public function reverseDeposit(QuotationDeposit $deposit, string $reason): array
    {
        if ($deposit->isReversed() || $deposit->applied_invoice_id) {
            return $this->failure(__('quotations::quotations.deposit_locked'));
        }

        $deposit->forceFill(['reversed_at' => now(), 'reverse_reason' => mb_substr($reason, 0, 500)])->save();
        audit(ActivityLogAction::Updated, __('quotations::quotations.audit_deposit_reversed', ['amount' => money($deposit->amount), 'reason' => $reason]), $deposit);

        return $this->success($deposit, __('quotations::quotations.deposit_reversed'));
    }

    /**
     * Invoicing's "invoice_created" hook: deposits of the quote behind the
     * invoiced order become payments on the invoice.
     */
    public function applyDeposits(object $invoice): void
    {
        if (! $invoice->sales_order_id || ! $this->modules->isActive('Invoicing')) {
            return;
        }

        $quote = Quotation::query()->where('sales_order_id', $invoice->sales_order_id)->first();

        if (! $quote) {
            return;
        }

        $payments = app(\Modules\Invoicing\Services\InvoiceService::class);

        foreach ($quote->deposits()->whereNull('reversed_at')->whereNull('applied_invoice_id')->get() as $deposit) {
            $result = $payments->addPayment($invoice->fresh(), [
                'amount' => min((float) $deposit->amount, $invoice->fresh()->balance()),
                'paid_on' => $deposit->paid_on->toDateString(),
                'method' => $deposit->method,
                'reference' => $deposit->reference,
                'note' => __('quotations::quotations.deposit_note', ['number' => $quote->number]),
            ]);

            if ($result['status']) {
                $deposit->forceFill(['applied_invoice_id' => $invoice->id])->save();
            }
        }
    }

    private function writeLines(Quotation $quote, array $lines): void
    {
        foreach (array_values($lines) as $position => $line) {
            $row = new QuotationLine([
                'variant_id' => $line['variant']->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
            $row->business_id = $quote->business_id;
            $row->quotation_id = $quote->id;
            $row->save();
        }

        $subtotal = (float) $quote->lines()->sum('line_total');
        $discount = min((float) $quote->discount, $subtotal);
        $quote->forceFill(['subtotal' => $subtotal, 'discount' => $discount, 'total' => round($subtotal - $discount, 2)])->save();
    }

    private function transition(Quotation $quote, array $changes): void
    {
        $before = ['status' => $quote->status?->label(), 'sample' => $quote->sample_status?->label()];
        $quote->forceFill($changes)->save();

        audit(ActivityLogAction::Updated, __('quotations::quotations.audit_status', [
            'number' => $quote->number,
            'from' => isset($changes['sample_status']) ? $before['sample'] : $before['status'],
            'to' => isset($changes['sample_status']) ? $quote->sample_status->label() : $quote->status->label(),
        ]), $quote);
    }
}
