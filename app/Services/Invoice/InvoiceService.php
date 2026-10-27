<?php

namespace App\Services\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Booking\Booking;
use App\Libraries\ListExport;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceLine;
use App\Repositories\GeneralLedger\GeneralLedgerRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\AccountAssignment\AccountAssignmentService;
use App\Services\BaseService;
use App\Services\GeneralLedger\LedgerPoster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceService extends BaseService
{
    public function __construct(
        protected InvoiceRepository $invoices,
        protected AccountAssignmentService $assignments,
        protected LedgerPoster $poster,
        protected GeneralLedgerRepository $ledgers,
    ) {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->invoices->query()
            ->with(['customer', 'corporateCustomer', 'booking'])
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->invoices->query()
            ->with(['customer', 'corporateCustomer'])
            ->latest('invoice_date')
            ->latest('id')
            ->get()
            ->map(fn (Invoice $invoice) => [
                $invoice->invoice_number,
                $invoice->invoice_date?->format('Y-m-d') ?? '—',
                $invoice->partyName(),
                number_format((float) $invoice->total, 0).' RWF',
                $invoice->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.invoices'), 'invoices', [
            __('body.number'),
            __('body.date'),
            __('body.customer'),
            __('body.amount'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $lines = $data['lines'] ?? [];
                unset($data['lines']);

                $subtotal = '0.00';
                foreach ($lines as $line) {
                    $lineTotal = bcmul((string) ($line['quantity'] ?? 1), (string) $line['unit_price'], 2);
                    $subtotal = bcadd($subtotal, $lineTotal, 2);
                }

                $invoice = new Invoice([
                    'invoice_number' => $this->nextNumber(),
                    'invoice_date' => $data['invoice_date'],
                    'customer_id' => $data['customer_id'] ?? null,
                    'corporate_customer_id' => $data['corporate_customer_id'] ?? null,
                    'booking_id' => $data['booking_id'] ?? null,
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'status' => InvoiceStatus::Draft->value,
                    'notes' => $data['notes'] ?? null,
                ]);
                $this->invoices->save($invoice);

                foreach ($lines as $line) {
                    $qty = (string) ($line['quantity'] ?? 1);
                    $price = (string) $line['unit_price'];
                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'description' => $line['description'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'total' => bcmul($qty, $price, 2),
                    ]);
                }

                return $this->success($invoice->load('lines'), __('body.invoice_created'));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($e->getMessage() ?: __('body.something_went_wrong'));
        }
    }

    /**
     * Draft invoice for a confirmed corporate (pay-later) booking.
     */
    public function createFromBooking(Booking $booking): array
    {
        $booking->loadMissing(['category', 'assignedVehicle', 'extras.extra', 'corporateCustomer', 'customer']);

        if ($existing = $booking->invoices()->first()) {
            return $this->success($existing, __('body.booking_invoice_created'));
        }

        $lines = [];
        $days = max(1, (int) $booking->rental_days);
        $vehicleLabel = $booking->assignedVehicle?->name() ?? $booking->category?->name ?? __('body.vehicle_rental');

        $lines[] = [
            'description' => $vehicleLabel.' ('.$days.' '.($days === 1 ? __('body.day') : __('body.days')).')',
            'quantity' => $days,
            'unit_price' => (string) $booking->category_daily_price,
        ];

        if (bccomp((string) ($booking->delivery_fee ?? 0), '0', 2) > 0) {
            $lines[] = [
                'description' => __('body.delivery_fee'),
                'quantity' => 1,
                'unit_price' => (string) $booking->delivery_fee,
            ];
        }

        if (bccomp((string) ($booking->collection_fee ?? 0), '0', 2) > 0) {
            $lines[] = [
                'description' => __('body.collection_fee'),
                'quantity' => 1,
                'unit_price' => (string) $booking->collection_fee,
            ];
        }

        if (bccomp((string) ($booking->chauffeur_fee ?? 0), '0', 2) > 0) {
            $lines[] = [
                'description' => __('body.chauffeur_fee_line', ['days' => $days]),
                'quantity' => 1,
                'unit_price' => (string) $booking->chauffeur_fee,
            ];
        }

        foreach ($booking->extras as $extraLine) {
            $lines[] = [
                'description' => ($extraLine->extra?->name ?? __('body.extras')).' × '.$extraLine->quantity,
                'quantity' => (int) $extraLine->quantity,
                'unit_price' => (string) $extraLine->unit_price,
            ];
        }

        $terms = $booking->corporateCustomer?->payment_terms;
        $notes = trim(implode("\n", array_filter([
            __('body.booking_number').': '.$booking->booking_number,
            $terms ? __('body.payment_terms').': '.$terms : null,
        ])));

        return $this->create([
            'invoice_date' => now()->toDateString(),
            'customer_id' => $booking->customer_id,
            'corporate_customer_id' => $booking->corporate_customer_id,
            'booking_id' => $booking->id,
            'notes' => $notes ?: null,
            'lines' => $lines,
        ]);
    }

    public function update(Invoice $invoice, array $data): array
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->failure(__('body.invoice_not_editable'));
        }

        try {
            return DB::transaction(function () use ($invoice, $data) {
                $lines = $data['lines'] ?? null;
                unset($data['lines']);

                $invoice->fill($data);

                if (is_array($lines)) {
                    $invoice->lines()->delete();
                    $subtotal = '0.00';
                    foreach ($lines as $line) {
                        $qty = (string) ($line['quantity'] ?? 1);
                        $price = (string) $line['unit_price'];
                        $total = bcmul($qty, $price, 2);
                        InvoiceLine::create([
                            'invoice_id' => $invoice->id,
                            'description' => $line['description'],
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'total' => $total,
                        ]);
                        $subtotal = bcadd($subtotal, $total, 2);
                    }
                    $invoice->subtotal = $subtotal;
                    $invoice->total = $subtotal;
                }

                $this->invoices->save($invoice);

                return $this->success($invoice->fresh('lines'), __('body.invoice_updated'));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($e->getMessage() ?: __('body.something_went_wrong'));
        }
    }

    public function issue(Invoice $invoice): array
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->failure(__('body.invoice_invalid_transition'));
        }

        $bookingAlreadyPosted = false;
        if ($invoice->booking_id) {
            $booking = Booking::query()->find($invoice->booking_id);
            if ($booking) {
                $bookingAlreadyPosted = (bool) $this->ledgers->findByReference('BK-CONF-'.$booking->uuid);
            }
        }

        if (! $bookingAlreadyPosted) {
            $ar = $this->assignments->requireAccount('invoice_issue', 'receivable');
            $revenue = $this->assignments->requireAccount('invoice_issue', 'revenue');
            $amount = (string) $invoice->total;

            $result = $this->poster->post(
                'INV-ISSUE-'.$invoice->uuid,
                'invoice_issue',
                $invoice->id,
                $invoice->invoice_date->toDateString(),
                'Invoice issue '.$invoice->invoice_number,
                [
                    ['account_id' => $ar->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            if (! ($result['status'] ?? false)) {
                return $result;
            }
        }

        $invoice->status = InvoiceStatus::Issued;
        $this->invoices->save($invoice);

        return $this->success($invoice, __('body.invoice_issued'));
    }

    public function cancel(Invoice $invoice): array
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true)) {
            return $this->failure(__('body.invoice_invalid_transition'));
        }

        if ($invoice->status === InvoiceStatus::Issued) {
            $this->poster->reverseByReference('INV-ISSUE-'.$invoice->uuid);
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $this->invoices->save($invoice);

        return $this->success($invoice, __('body.invoice_cancelled'));
    }

    public function delete(Invoice $invoice): array
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->failure(__('body.invoice_cannot_delete'));
        }

        $invoice->lines()->delete();
        $invoice->delete();

        return $this->success(null, __('body.invoice_deleted'));
    }

    public function markPaid(Invoice $invoice): void
    {
        $invoice->status = InvoiceStatus::Paid;
        $this->invoices->save($invoice);
    }

    protected function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $last = $this->invoices->query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
