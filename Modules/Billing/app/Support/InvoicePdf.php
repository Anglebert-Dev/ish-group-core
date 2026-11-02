<?php

namespace Modules\Billing\Support;

use App\Services\Printing\PrintService;
use App\Services\Setting\SettingService;
use App\Support\Printing\Document;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Symfony\Component\HttpFoundation\Response;

/**
 * A subscription invoice, through the core print engine: branded as the
 * platform business, with Billing's issuer details (when set) printed
 * instead of its own. "?print=1" gives the print view.
 */
class InvoicePdf
{
    public static function document(Invoice $invoice): Document
    {
        $invoice->loadMissing(['lines', 'business']);
        $money = fn ($amount) => Money::format($amount, $invoice->currency);
        $billed = (array) $invoice->billed_to;

        $document = Document::make('billing_invoice', __('billing::billing.invoice'))
            ->number($invoice->number)
            ->issuer(array_filter([
                'legalName' => BillingSettings::get('issuer_name') ?: null,
                'address' => BillingSettings::get('issuer_address') ?: null,
                'taxId' => BillingSettings::get('issuer_tax_id') ?: null,
                'email' => BillingSettings::get('issuer_email') ?: null,
                'phone' => BillingSettings::get('issuer_phone') ?: null,
            ]))
            ->meta(__('billing::billing.issued'), $invoice->issued_at?->format('d M Y'))
            ->meta(__('billing::billing.due'), $invoice->due_at?->format('d M Y'))
            ->meta(__('billing::billing.paid_on'), $invoice->paid_at?->format('d M Y'))
            ->meta(__('billing::billing.period'), $invoice->period_start && $invoice->period_end ? $invoice->period_start->format('d M Y').' – '.$invoice->period_end->format('d M Y') : null)
            ->party(__('billing::billing.billed_to'), $billed['name'] ?? $invoice->business?->name, [
                $billed['address'] ?? null,
                ! empty($billed['tax_id']) ? __('body.brand_tax_id_short').' '.$billed['tax_id'] : null,
                $billed['email'] ?? null,
            ])
            ->table([[__('billing::billing.description')], [__('billing::billing.amount'), 'right']],
                $invoice->lines->map(fn ($line) => [$line->description, $money($line->amount)])->all())
            ->total(__('billing::billing.subtotal'), $money($invoice->subtotal));

        if ((float) $invoice->discount > 0) {
            $document->total(__('billing::billing.discount'), '− '.$money($invoice->discount));
        }

        if ((float) $invoice->credit > 0) {
            $document->total(__('billing::billing.credit'), '− '.$money($invoice->credit));
        }

        if ((float) $invoice->tax > 0) {
            $document->total(($invoice->tax_label ?: __('billing::billing.tax')).' '.rtrim(rtrim((string) $invoice->tax_rate, '0'), '.').'%'.($invoice->tax_inclusive ? ' ('.__('billing::billing.included').')' : ''), $money($invoice->tax));
        }

        $document->total(__('billing::billing.total'), $money($invoice->total), strong: true);

        if ((float) $invoice->amount_paid > 0) {
            $document->total(__('billing::billing.amount_paid'), $money($invoice->amount_paid))
                ->total(__('billing::billing.balance_due'), $money(Money::fromCents($invoice->balanceCents())), strong: true);
        }

        if ($invoice->isOpen()) {
            $document->section(__('billing::billing.payment_instructions'), [BillingSettings::get('payment_instructions') ?: null]);
        }

        return $document
            ->notes($invoice->notes)
            ->watermark(match ($invoice->status) {
                InvoiceStatus::Paid => __('billing::billing.watermark_paid'),
                InvoiceStatus::Void => __('billing::billing.watermark_void'),
                default => null,
            })
            ->withPayment($invoice->isOpen());
    }

    public static function render(Invoice $invoice): string
    {
        return app(PrintService::class)->pdfBytes(self::document($invoice), self::platform());
    }

    /**
     * PDF download, or the print view with "?print=1".
     */
    public static function download(Invoice $invoice): Response
    {
        $print = request()->boolean('print');

        app(\App\Services\ActivityLog\ActivityLogService::class)->log(\App\Enums\ActivityLogAction::Downloaded, __('body.audit_log_invoice_pdf', ['number' => $invoice->number]), $invoice, ['format' => $print ? 'print' : 'pdf'], $invoice->business_id);

        $printer = app(PrintService::class);

        return $print
            ? response($printer->html(self::document($invoice), self::platform(), request()->fullUrlWithoutQuery('print'))->render())
            : $printer->pdf(self::document($invoice), self::platform(), $invoice->number);
    }

    /** Subscription invoices are issued by the platform, so they carry its brand. */
    private static function platform(): \App\Models\Business\Business
    {
        return app(SettingService::class)->primary() ?? app(SettingService::class)->current();
    }
}
