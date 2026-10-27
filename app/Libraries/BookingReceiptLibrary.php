<?php

namespace App\Libraries;

use App\Models\Booking\Booking;
use App\Services\Setting\SettingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

class BookingReceiptLibrary
{
    public static function pdf(Booking $booking): Response
    {
        $booking->loadMissing(['extras.extra', 'pickupStation', 'returnStation', 'category', 'customer', 'assignedVehicle']);

        $branding = app(SettingService::class)->current();
        $logoPath = self::logoPath($branding->logo_path);

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', false);
        $options->setChroot([base_path(), storage_path('app/public')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('public.booking.receipt-pdf', [
            'booking' => $booking,
            'company' => $branding->companyName(),
            'brandColor' => $branding->brandColor(),
            'logoPath' => $logoPath,
            'generatedAt' => GeneralLibrary::formatDateTime(now()),
        ])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $filename = 'booking-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $booking->booking_number).'-invoice.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private static function logoPath(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }

        $absolute = storage_path('app/public/'.$logoPath);

        return is_file($absolute) ? $absolute : null;
    }
}
