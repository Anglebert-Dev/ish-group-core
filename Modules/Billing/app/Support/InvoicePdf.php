<?php

namespace Modules\Billing\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Modules\Billing\Models\Invoice;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdf
{
    public static function render(Invoice $invoice): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('billing::pdf.invoice', [
            'invoice' => $invoice->loadMissing(['lines', 'payments']),
            'issuer' => [
                'name' => BillingSettings::get('issuer_name') ?: config('app.name'),
                'address' => BillingSettings::get('issuer_address'),
                'tax_id' => BillingSettings::get('issuer_tax_id'),
                'email' => BillingSettings::get('issuer_email'),
                'phone' => BillingSettings::get('issuer_phone'),
            ],
            'instructions' => BillingSettings::get('payment_instructions'),
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function download(Invoice $invoice): Response
    {
        app(\App\Services\ActivityLog\ActivityLogService::class)->log(\App\Enums\ActivityLogAction::Downloaded, __('body.audit_log_invoice_pdf', ['number' => $invoice->number]), $invoice, [], $invoice->business_id);

        return response(self::render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
