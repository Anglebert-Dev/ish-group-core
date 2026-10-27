<?php

namespace App\Services\Payment;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Libraries\ListExport;
use App\Models\Booking\Booking;
use App\Models\Expense\Expense;
use App\Models\Invoice\Invoice;
use App\Models\Payment\Payment;
use App\Repositories\Payment\PaymentRepository;
use App\Services\AccountAssignment\AccountAssignmentService;
use App\Services\BaseService;
use App\Services\GeneralLedger\BookingLedgerService;
use App\Services\GeneralLedger\LedgerPoster;
use App\Services\Invoice\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentService extends BaseService
{
    public function __construct(
        protected PaymentRepository $payments,
        protected AccountAssignmentService $assignments,
        protected LedgerPoster $poster,
        protected BookingLedgerService $bookingLedger,
        protected InvoiceService $invoices,
    ) {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->payments->query()
            ->with(['booking', 'invoice', 'expense', 'supplier'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->payments->query()
            ->latest('payment_date')
            ->latest('id')
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->payment_number,
                $payment->payment_date?->format('Y-m-d') ?? '—',
                number_format((float) $payment->amount, 0).' RWF',
                $payment->method->label(),
                $payment->status->label(),
            ])
            ->all();

        return ListExport::make(__('body.payments'), 'payments', [
            __('body.number'),
            __('body.date'),
            __('body.amount'),
            __('body.method'),
            __('body.status'),
        ], $rows);
    }

    public function create(array $data): array
    {
        $targets = collect([
            $data['booking_id'] ?? null,
            $data['invoice_id'] ?? null,
            $data['expense_id'] ?? null,
            $data['supplier_id'] ?? null,
        ])->filter()->count();

        if ($targets < 1) {
            return $this->failure(__('body.payment_target_required'));
        }

        try {
            return DB::transaction(function () use ($data) {
                $payment = new Payment([
                    'payment_number' => $this->nextNumber(),
                    'payment_date' => $data['payment_date'],
                    'amount' => $data['amount'],
                    'method' => $data['method'],
                    'booking_id' => $data['booking_id'] ?? null,
                    'invoice_id' => $data['invoice_id'] ?? null,
                    'expense_id' => $data['expense_id'] ?? null,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'status' => PaymentStatus::Draft->value,
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
                $this->payments->save($payment);

                $post = $this->post($payment);
                if (! ($post['status'] ?? false)) {
                    throw new \RuntimeException($post['message'] ?? __('body.something_went_wrong'));
                }

                return $this->success($post['data'], __('body.payment_created'));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($e->getMessage() ?: __('body.something_went_wrong'));
        }
    }

    public function post(Payment $payment): array
    {
        if ($payment->status === PaymentStatus::Posted) {
            return $this->success($payment, __('body.payment_already_posted'));
        }

        $amount = (string) $payment->amount;
        $date = $payment->payment_date->toDateString();

        if ($payment->booking_id) {
            $booking = Booking::query()->findOrFail($payment->booking_id);
            $result = $this->bookingLedger->postPayment($booking, $amount, 'pay-'.$payment->uuid);
            if (! ($result['status'] ?? false)) {
                return $result;
            }
        } elseif ($payment->invoice_id) {
            $invoice = Invoice::query()->findOrFail($payment->invoice_id);
            $cash = $this->assignments->requireAccount('invoice_payment', 'cash');
            $ar = $this->assignments->requireAccount('invoice_payment', 'receivable');
            $result = $this->poster->post(
                'PAY-INV-'.$payment->uuid,
                'invoice_payment',
                $payment->id,
                $date,
                'Invoice payment '.$invoice->invoice_number,
                [
                    ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount],
                ],
            );
            if (! ($result['status'] ?? false)) {
                return $result;
            }
            if (in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Draft], true)) {
                $this->invoices->markPaid($invoice);
            }
        } elseif ($payment->expense_id || $payment->supplier_id) {
            $payment->loadMissing('expense');
            $payable = $this->assignments->requireAccount('supplier_pay', 'payable');
            $cash = $this->assignments->requireAccount('supplier_pay', 'cash');
            $label = $payment->expense?->expense_number ?? ('Supplier #'.($payment->supplier_id));
            $result = $this->poster->post(
                'PAY-SUP-'.$payment->uuid,
                'supplier_pay',
                $payment->id,
                $date,
                'Supplier payment '.$label,
                [
                    ['account_id' => $payable->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
                ],
            );
            if (! ($result['status'] ?? false)) {
                return $result;
            }
            if ($payment->expense_id) {
                $expense = Expense::query()->find($payment->expense_id);
                if ($expense && $expense->status === ExpenseStatus::Posted) {
                    $expense->paid = true;
                    $expense->save();
                }
            }
        } else {
            return $this->failure(__('body.payment_target_required'));
        }

        $payment->status = PaymentStatus::Posted;
        $this->payments->save($payment);

        return $this->success($payment->fresh(['booking', 'invoice', 'expense', 'supplier']), __('body.payment_posted'));
    }

    public function delete(Payment $payment): array
    {
        if ($payment->status === PaymentStatus::Posted) {
            return $this->failure(__('body.payment_cannot_delete_posted'));
        }

        $payment->delete();

        return $this->success(null, __('body.payment_deleted'));
    }

    protected function nextNumber(): string
    {
        $prefix = 'PAY-'.now()->format('Ymd').'-';
        $last = $this->payments->query()
            ->where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('payment_number')
            ->value('payment_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
