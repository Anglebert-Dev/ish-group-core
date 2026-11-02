<?php

namespace App\Libraries;

use App\Services\Printing\PrintService;
use App\Services\Setting\SettingService;
use App\Support\Printing\Document;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * List exports (every table's PDF / Excel / Print buttons), through the one
 * print engine so they carry the business's brand and design
 * (docs/branding-print-build-guide.md §4). "?print=1" on a PDF link gives
 * the browser print view instead of a download.
 */
class ListExportLibrary
{
    public static function pdf(ListExport $export): Response
    {
        $business = app(SettingService::class)->current();
        $printer = app(PrintService::class);
        $document = self::document($export);

        if (request()->boolean('print')) {
            self::audit($export, 'Print');

            return response($printer->html($document, $business, request()->fullUrlWithoutQuery('print'))->render());
        }

        self::audit($export, 'PDF');

        return $printer->pdf($document, $business, $export->downloadName('pdf'));
    }

    public static function excel(ListExport $export): StreamedResponse
    {
        self::audit($export, 'Excel');

        return app(PrintService::class)->excel(self::document($export), app(SettingService::class)->current(), $export->downloadName('xlsx'));
    }

    /**
     * A list as a document: wide lists print landscape; columns that hold
     * only numbers or amounts are right-aligned.
     */
    public static function document(ListExport $export): Document
    {
        $columns = [];

        foreach ($export->headings as $i => $heading) {
            $values = array_filter(array_column($export->rows, $i), fn ($value) => trim((string) $value) !== '' && trim((string) $value) !== '—');
            $numeric = $values !== [] && collect($values)->every(fn ($value) => preg_match('/^[A-Z]{0,3}\s?-?[\d,.\s]+%?$/u', trim((string) $value)) === 1);
            $columns[] = [$heading, $numeric ? 'right' : 'left'];
        }

        return Document::make('list', $export->title)
            ->meta(__('body.print_rows'), (string) count($export->rows))
            ->table($columns, $export->rows)
            ->landscape(count($export->headings) > 5)
            ->whenEmpty(__('body.no_records_to_export'));
    }

    private static function audit(ListExport $export, string $format): void
    {
        app(\App\Services\ActivityLog\ActivityLogService::class)->log(
            \App\Enums\ActivityLogAction::Exported,
            __('body.audit_log_exported', ['list' => $export->title, 'format' => $format, 'rows' => count($export->rows)]),
            null,
            ['list' => $export->title, 'format' => $format, 'rows' => count($export->rows)],
        );
    }
}
