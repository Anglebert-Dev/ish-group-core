<?php

namespace App\Services\Printing;

use App\Libraries\ColorLibrary;
use App\Libraries\GeneralLibrary;
use App\Models\Business\Business;
use App\Services\Branding\BrandingService;
use App\Support\Branding\Brand;
use App\Support\Branding\Fonts;
use App\Support\Printing\Document;
use App\Support\Printing\PrintDesign;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one print engine (docs/branding-print-build-guide.md §4): a Document,
 * in a business's brand and design, as PDF, a print view or Excel.
 */
class PrintService
{
    public function __construct(protected BrandingService $branding) {}

    /**
     * Everything the template needs.
     *
     * @return array<string, mixed>
     */
    public function data(Document $document, Business $business, ?PrintDesign $design = null, string $mode = 'pdf'): array
    {
        $brand = $this->branding->brand($business);
        $design = ($design ?? PrintDesign::for($business, $brand))->forType($document->type);
        $brand = $document->issuer ? $brand->with($document->issuer) : $brand;
        $dark = $design->layout === 'modern';

        return [
            'doc' => $document,
            'brand' => $brand,
            'design' => $design,
            'mode' => $mode,
            'logo' => $brand->logoDataUri(dark: $dark),
            'stamp' => $design->showStamp && $document->signature ? $brand->stampDataUri() : null,
            'signature' => $design->showSignature && $document->signature ? $brand->signatureDataUri() : null,
            'accentLight' => ColorLibrary::lighten($design->accent, 0.9),
            'accentDark' => ColorLibrary::darken($design->accent),
            'terms' => $design->termsFor($document->type),
            'footer' => $design->footerFor($document->type),
            'printedAt' => GeneralLibrary::formatDateTime(now()),
        ];
    }

    public function pdfBytes(Document $document, Business $business, ?PrintDesign $design = null): string
    {
        $data = $this->data($document, $business, $design, 'pdf');

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([base_path(), public_path(), storage_path('app/public')]);
        // The bundled brand fonts are measured once and cached here.
        $options->set('fontDir', $this->fontCache());
        $options->set('fontCache', $this->fontCache());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('print.document', $data)->render());
        $dompdf->setPaper($data['design']->paper, $document->orientation);
        $dompdf->render();

        // "Page 1 of 3", bottom right: CSS can't count pages in dompdf.
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $text = __('body.print_page', ['page' => '{PAGE_NUM}', 'pages' => '{PAGE_COUNT}']);
        $canvas->page_text($canvas->get_width() - 42 - 40, $canvas->get_height() - 31, $text, $font, 7, [0.42, 0.45, 0.5]);

        return $dompdf->output();
    }

    private function fontCache(): string
    {
        $dir = storage_path('fonts');
        is_dir($dir) || @mkdir($dir, 0755, true);

        return $dir;
    }

    public function pdf(Document $document, Business $business, string $filename, bool $inline = false, ?PrintDesign $design = null): Response
    {
        return response($this->pdfBytes($document, $business, $design), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$this->filename($filename, 'pdf').'"',
        ]);
    }

    /**
     * The print view: the same page in the browser, with Print and PDF buttons
     * (hidden on paper) and the print dialog opened straight away.
     */
    public function html(Document $document, Business $business, ?string $pdfUrl = null, bool $autoPrint = true, ?PrintDesign $design = null): View
    {
        return view('print.document', $this->data($document, $business, $design, 'html') + ['pdfUrl' => $pdfUrl, 'autoPrint' => $autoPrint]);
    }

    /**
     * The table as a branded spreadsheet: logo, business details, title and
     * meta above, header row in the accent colour, totals below.
     */
    public function excel(Document $document, Business $business, string $filename): StreamedResponse
    {
        $brand = $this->branding->brand($business);
        $design = PrintDesign::for($business, $brand);
        $spreadsheet = new Spreadsheet;
        // Excel uses the brand font when the computer has it, else its own.
        $spreadsheet->getDefaultStyle()->getFont()->setName(Fonts::family($design->font) ?? 'Calibri');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $document->title), 28, ''));
        $columns = max(1, count($document->columns));
        $last = self::column($columns);
        $row = 1;

        if ($logo = $this->logoFile($brand)) {
            $drawing = new Drawing;
            $drawing->setPath($logo);
            $drawing->setHeight(40);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(34);
            $row = 3;
        }

        $line = function (string $text, array $font = []) use ($sheet, &$row, $last) {
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->applyFromArray($font);
            $row++;
        };

        $line($brand->documentName(), ['bold' => true, 'size' => 14, 'color' => ['rgb' => ltrim($design->accent, '#')]]);

        foreach ($brand->contactLines() as $contact) {
            $line(str_replace("\n", ', ', $contact), ['size' => 9, 'color' => ['rgb' => '667085']]);
        }

        $row++;
        $line(trim($document->title.' '.($document->number ?? '')), ['bold' => true, 'size' => 12]);

        foreach ($document->meta as $meta) {
            $line($meta['label'].': '.$meta['value'], ['size' => 9]);
        }

        foreach ($document->parties as $party) {
            $line($party['label'].': '.$party['name'].($party['lines'] ? ' — '.implode(', ', $party['lines']) : ''), ['size' => 9]);
        }

        $line(__('body.generated_at').': '.GeneralLibrary::formatDateTime(now()), ['italic' => true, 'size' => 8, 'color' => ['rgb' => '667085']]);
        $row++;

        $header = $row;
        foreach ($document->columns as $i => $column) {
            $sheet->setCellValue(self::column($i + 1).$header, $column['label']);
        }

        $sheet->getStyle("A{$header}:{$last}{$header}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim($design->accent, '#')]],
        ]);

        $row = $header + 1;

        foreach ($document->rows as $cells) {
            foreach ($cells as $i => $value) {
                $sheet->setCellValueExplicit(self::column($i + 1).$row, $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        $end = max($header, $row - 1);
        $sheet->getStyle("A{$header}:{$last}{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D5DD');

        foreach ($document->columns as $i => $column) {
            if ($column['align'] === 'right') {
                $sheet->getStyle(self::column($i + 1).($header + 1).':'.self::column($i + 1).$end)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        foreach ($document->totals as $total) {
            $sheet->setCellValue(self::column(max(1, $columns - 1)).$row, $total['label']);
            $sheet->setCellValueExplicit("{$last}{$row}", $total['value'], DataType::TYPE_STRING);
            $sheet->getStyle(self::column(max(1, $columns - 1)).$row.":{$last}{$row}")->getFont()->setBold($total['strong']);
            $sheet->getStyle("{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        if ($document->columns !== []) {
            $sheet->freezePane('A'.($header + 1));
            $sheet->setAutoFilter("A{$header}:{$last}{$end}");
        }

        for ($i = 1; $i <= $columns; $i++) {
            $sheet->getColumnDimension(self::column($i))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $this->filename($filename, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function filename(string $name, string $extension): string
    {
        $name = Str::slug(preg_replace('/\.(pdf|xlsx)$/i', '', $name)) ?: 'document';

        return $name.'.'.$extension;
    }

    /** A local copy of the logo for PhpSpreadsheet (it reads from disk). */
    private function logoFile(Brand $brand): ?string
    {
        if (! $brand->logoPath) {
            return null;
        }

        $path = Storage::disk('public')->path($brand->logoPath);

        return is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif'], true) ? $path : null;
    }

    private static function column(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }
}
