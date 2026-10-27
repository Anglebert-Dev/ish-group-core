<?php

namespace Modules\Billing\Services;

use App\Models\Business\Business;
use App\Services\BaseService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceReason;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\BillingSettings;
use Modules\Billing\Support\InvoiceTotals;
use Modules\Billing\Support\Money;
use RuntimeException;

class InvoiceService extends BaseService
{
    public function account(Business $business): BillingAccount
    {
        return BillingAccount::query()->firstOrCreate(
            ['business_id' => $business->id],
            ['billing_name' => $business->name, 'billing_email' => $business->owner?->email],
        );
    }

    /**
     * What an invoice for $package would contain — used both to issue it
     * and to show a checkout preview without saving anything.
     *
     * @return array{lines: list<array{type: string, description: string, unit_amount: int, amount: int}>, totals: InvoiceTotals, credit_from_balance: int, proration: int}
     */
    public function compose(
        Business $business,
        Package $package,
        ?Coupon $coupon = null,
        int $prorationCredit = 0,
        bool $useBalance = true,
        ?CarbonInterface $periodStart = null,
        ?CarbonInterface $periodEnd = null,
    ): array {
        $price = Money::toCents($package->price);
        $period = $periodStart && $periodEnd
            ? ' ('.$periodStart->format('d M Y').' – '.$periodEnd->format('d M Y').')'
            : ' ('.$package->intervalLabel().')';

        $lines = [[
            'type' => 'package',
            'description' => $package->name.$period,
            'unit_amount' => $price,
            'amount' => $price,
        ]];

        $discount = $coupon ? $coupon->discountCents($price) : 0;
        if ($discount > 0) {
            $lines[] = [
                'type' => 'discount',
                'description' => __('billing::billing.line_discount', ['code' => $coupon->code, 'summary' => $coupon->summary()]),
                'unit_amount' => -$discount,
                'amount' => -$discount,
            ];
        }

        if ($prorationCredit > 0) {
            $lines[] = [
                'type' => 'proration',
                'description' => __('billing::billing.line_proration'),
                'unit_amount' => -$prorationCredit,
                'amount' => -$prorationCredit,
            ];
        }

        $room = max(0, $price - $discount - $prorationCredit);
        $fromBalance = $useBalance ? min($room, Money::toCents($this->account($business)->credit_balance)) : 0;

        if ($fromBalance > 0) {
            $lines[] = [
                'type' => 'adjustment',
                'description' => __('billing::billing.line_balance'),
                'unit_amount' => -$fromBalance,
                'amount' => -$fromBalance,
            ];
        }

        return [
            'lines' => $lines,
            'totals' => InvoiceTotals::calculate($price, $discount, $prorationCredit + $fromBalance),
            'credit_from_balance' => $fromBalance,
            'proration' => $prorationCredit,
        ];
    }

    /**
     * Issue an invoice. Account credit used is taken from the balance now
     * and given back if the invoice is voided.
     */
    public function issue(
        Business $business,
        Subscription $subscription,
        Package $package,
        InvoiceReason $reason,
        ?Coupon $coupon = null,
        int $prorationCredit = 0,
        ?CarbonInterface $periodStart = null,
        ?CarbonInterface $periodEnd = null,
    ): Invoice {
        return DB::transaction(function () use ($business, $subscription, $package, $reason, $coupon, $prorationCredit, $periodStart, $periodEnd) {
            $draft = $this->compose($business, $package, $coupon, $prorationCredit, true, $periodStart, $periodEnd);
            $totals = $draft['totals'];
            $account = $this->account($business);

            $invoice = new Invoice([
                'business_id' => $business->id,
                'subscription_id' => $subscription->id,
                'reason' => $reason,
                'package_id' => $package->id,
                'status' => InvoiceStatus::Open,
                'currency' => BillingSettings::get('currency'),
                'subtotal' => Money::fromCents($totals->subtotal),
                'discount' => Money::fromCents($totals->discount),
                'credit' => Money::fromCents($totals->credit),
                'credit_from_balance' => Money::fromCents($draft['credit_from_balance']),
                // Proration larger than this invoice: credited to the account once paid.
                'proration_excess' => Money::fromCents(max(0, $draft['proration'] - ($totals->credit - $draft['credit_from_balance']))),
                'tax_rate' => $totals->taxRate,
                'tax_inclusive' => $totals->taxInclusive,
                'tax_label' => BillingSettings::get('tax_label'),
                'tax' => Money::fromCents($totals->tax),
                'total' => Money::fromCents($totals->total),
                'amount_paid' => '0.00',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => now()->addDays(max(0, BillingSettings::int('due_days'))),
                'billed_to' => [
                    'name' => $account->billing_name ?: $business->name,
                    'email' => $account->billing_email ?: $business->owner?->email,
                    'tax_id' => $account->tax_id,
                    'address' => $account->address,
                ],
            ]);
            $invoice->number = $this->nextNumber();
            $invoice->save();

            foreach ($draft['lines'] as $line) {
                $invoice->lines()->create([
                    'type' => $line['type'],
                    'description' => $line['description'],
                    'quantity' => 1,
                    'unit_amount' => Money::fromCents($line['unit_amount']),
                    'amount' => Money::fromCents($line['amount']),
                ]);
            }

            if ($draft['credit_from_balance'] > 0) {
                $account->credit_balance = Money::fromCents(Money::toCents($account->credit_balance) - $draft['credit_from_balance']);
                $account->save();
            }

            return $invoice;
        });
    }

    public function void(Invoice $invoice, ?string $reason = null): array
    {
        if (! $invoice->isOpen()) {
            return $this->failure(__('billing::billing.invoice_not_open'));
        }

        if (Money::toCents($invoice->amount_paid) > 0) {
            return $this->failure(__('billing::billing.invoice_partly_paid'));
        }

        DB::transaction(function () use ($invoice, $reason) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').($reason ?? '')) ?: null,
            ])->save();

            // Give back account credit that this invoice had used.
            $restore = Money::toCents($invoice->credit_from_balance);
            if ($restore > 0) {
                $account = $this->account($invoice->business);
                $account->credit_balance = Money::fromCents(Money::toCents($account->credit_balance) + $restore);
                $account->save();
            }
        });

        return $this->success($invoice, __('billing::billing.invoice_voided'));
    }

    public function addCredit(Business $business, int $cents): void
    {
        if ($cents <= 0) {
            return;
        }

        $account = $this->account($business);
        $account->credit_balance = Money::fromCents(Money::toCents($account->credit_balance) + $cents);
        $account->save();
    }

    /**
     * PREFIX-2026-00001, sequential per year.
     */
    private function nextNumber(): string
    {
        $prefix = strtoupper(trim(BillingSettings::get('invoice_prefix')) ?: 'INV').'-'.now()->format('Y').'-';

        $last = Invoice::withTrashed()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = $prefix.str_pad((string) ($next + $attempt), 5, '0', STR_PAD_LEFT);

            if (! Invoice::withTrashed()->where('number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Could not allocate an invoice number.');
    }
}
