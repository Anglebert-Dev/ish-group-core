<?php

namespace Modules\Billing\Support;

/**
 * Totals for an invoice, in cents.
 *
 *   net   = subtotal - discount - credit   (never below 0)
 *   exclusive tax: tax = net * rate, total = net + tax
 *   inclusive tax: total = net, tax = net - net / (1 + rate)
 */
class InvoiceTotals
{
    public function __construct(
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $credit,
        public readonly int $tax,
        public readonly int $total,
        public readonly float $taxRate,
        public readonly bool $taxInclusive,
    ) {
    }

    public static function calculate(int $subtotal, int $discount = 0, int $credit = 0, ?float $taxRate = null, ?bool $inclusive = null): self
    {
        $taxRate ??= (float) BillingSettings::get('tax_rate');
        $inclusive ??= BillingSettings::bool('tax_inclusive');

        $discount = max(0, min($subtotal, $discount));
        $credit = max(0, min($subtotal - $discount, $credit));
        $net = $subtotal - $discount - $credit;
        $rate = max(0.0, $taxRate) / 100;

        if ($inclusive) {
            $tax = $rate > 0 ? (int) round($net - $net / (1 + $rate)) : 0;
            $total = $net;
        } else {
            $tax = (int) round($net * $rate);
            $total = $net + $tax;
        }

        return new self($subtotal, $discount, $credit, $tax, $total, $taxRate, $inclusive);
    }
}
