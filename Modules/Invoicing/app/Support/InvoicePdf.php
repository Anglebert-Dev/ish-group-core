<?php

namespace Modules\Invoicing\Support;

use App\Support\Settings\BusinessSettings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Modules\Invoicing\Models\SalesInvoice;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdf
{
    public static function download(SalesInvoice $invoice): Response
    {
        $settings = app(BusinessSettings::class);
        $business = $invoice->business;

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('invoicing::pdf.invoice', [
            'invoice' => $invoice->loadMissing(['lines.variant.item', 'customer', 'payments']),
            'issuer' => [
                'name' => $business?->companyName(),
                'tax_id' => $settings->get('invoicing.tax_id', null, $business),
                'address' => $settings->get('invoicing.address', null, $business),
                'phone' => $settings->get('invoicing.phone', null, $business),
                'email' => $business?->contactEmail(),
                'color' => $business?->brandColor() ?? '#124EB0',
            ],
            'footer' => $settings->get('invoicing.footer', null, $business),
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        audit(\App\Enums\ActivityLogAction::Downloaded, __('invoicing::invoicing.audit_pdf', ['number' => $invoice->number]), $invoice);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
