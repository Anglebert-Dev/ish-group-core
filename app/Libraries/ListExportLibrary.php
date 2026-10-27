<?php

namespace App\Libraries;

use App\Services\Setting\SettingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExportLibrary
{
    public static function pdf(ListExport $export): Response
    {
        $branding = app(SettingService::class)->current();
        $logoSrc = self::logoDataUri($branding->logo_path);

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot([base_path(), public_path(), storage_path('app/public')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('components.common.list-export-pdf', [
            'export' => $export,
            'company' => $branding->companyName(),
            'brandColor' => $branding->brandColor(),
            'logoPath' => $logoSrc,
            'generatedAt' => GeneralLibrary::formatDateTime(now()),
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$export->downloadName('pdf').'"',
        ]);
    }

    public static function excel(ListExport $export): StreamedResponse
    {
        $branding = app(SettingService::class)->current();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($export->title, 0, 31));

        $columnCount = max(1, count($export->headings));
        $lastColumn = self::columnLetter($columnCount);

        $sheet->setCellValue('A1', $branding->companyName());
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', $export->title);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('A3', __('body.generated_at').': '.GeneralLibrary::formatDateTime(now()));
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

        $headerRow = 5;
        foreach ($export->headings as $index => $heading) {
            $sheet->setCellValue(self::columnLetter($index + 1).$headerRow, $heading);
        }

        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => ltrim($branding->brandColor(), '#')],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $rowNumber = $headerRow + 1;
        foreach ($export->rows as $row) {
            foreach ($row as $index => $value) {
                $sheet->setCellValueExplicit(
                    self::columnLetter($index + 1).$rowNumber,
                    (string) $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
            $rowNumber++;
        }

        $dataEnd = max($headerRow, $rowNumber - 1);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$dataEnd}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D5DD'],
                ],
            ],
        ]);

        $sheet->freezePane('A6');
        $sheet->setAutoFilter($headerRange);

        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimension(self::columnLetter($i))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $export->downloadName('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function logoDataUri(?string $logoPath): ?string
    {
        $absolute = null;

        if (filled($logoPath)) {
            $candidate = storage_path('app/public/'.$logoPath);
            if (is_file($candidate)) {
                $absolute = $candidate;
            }
        }

        if ($absolute === null) {
            return null;
        }

        $mime = @mime_content_type($absolute) ?: 'image/png';
        $binary = @file_get_contents($absolute);

        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
