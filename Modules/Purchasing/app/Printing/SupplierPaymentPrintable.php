<?php

namespace Modules\Purchasing\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Purchasing\Models\SupplierPayment;

/**
 * Payment voucher for money paid to a supplier.
 */
class SupplierPaymentPrintable extends Printable
{
    public function label(): string
    {
        return __('purchasing::purchasing.print_voucher');
    }

    public function permission(): string
    {
        return 'read purchasing.payment';
    }

    public function find(string $key): Model
    {
        return $this->lookup(SupplierPayment::class, $key, ['supplier']);
    }

    /**
     * @param  SupplierPayment  $payment
     */
    public function document(Model $payment): Document
    {
        return PurchaseOrderPrintable::supplier(Document::make('supplier_payment', __('purchasing::purchasing.print_voucher')), __('purchasing::purchasing.print_paid_to'), $payment->supplier)
            ->number($payment->number)
            ->meta(__('purchasing::purchasing.print_paid_on'), $payment->paid_on?->format('d M Y'))
            ->meta(__('purchasing::purchasing.print_method'), $payment->method ? __('purchasing::purchasing.method_'.$payment->method) : null)
            ->meta(__('purchasing::purchasing.print_reference'), $payment->reference)
            ->table([[__('purchasing::purchasing.print_description')], [__('inventory::inventory.line_total'), 'right']], [
                [__('purchasing::purchasing.print_payment_to', ['name' => $payment->supplier?->name]), money($payment->amount)],
            ])
            ->total(__('purchasing::purchasing.print_amount_paid'), money($payment->amount), strong: true)
            ->notes($payment->note)
            ->section(__('purchasing::purchasing.print_received_by'), ['________________________________'])
            ->watermark($payment->isReversed() ? __('purchasing::purchasing.print_watermark_reversed') : null)
            ->withSignature();
    }
}
