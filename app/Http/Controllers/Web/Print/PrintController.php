<?php

namespace App\Http\Controllers\Web\Print;

use App\Enums\ActivityLogAction;
use App\Http\Controllers\Controller;
use App\Services\Printing\PrintService;
use App\Support\Printing\PrintRegistry;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * /app/print/{type}/{id}: any registered document, in the business's brand
 * (docs/branding-print-build-guide.md §4). The print view opens the browser's
 * print dialog; /pdf downloads it.
 */
class PrintController extends Controller
{
    public function __construct(
        protected PrintRegistry $registry,
        protected PrintService $printer,
    ) {
    }

    public function show(string $type, string $id): View
    {
        [$printable, $record] = $this->resolve($type, $id);
        $document = $printable->document($record);

        audit(ActivityLogAction::Downloaded, __('body.print_audit_printed', ['document' => trim($document->title.' '.$document->number)]), $record, ['type' => $type, 'format' => 'print']);

        return $this->printer->html($document, $printable->business($record), route('print.pdf', [$type, $id]));
    }

    public function pdf(string $type, string $id): Response
    {
        [$printable, $record] = $this->resolve($type, $id);
        $document = $printable->document($record);

        audit(ActivityLogAction::Downloaded, __('body.print_audit_pdf', ['document' => trim($document->title.' '.$document->number)]), $record, ['type' => $type, 'format' => 'pdf']);

        return $this->printer->pdf($document, $printable->business($record), $printable->filename($record));
    }

    private function resolve(string $type, string $id): array
    {
        $printable = $this->registry->get($type);
        abort_unless($printable, 404);
        abort_unless(auth('staff')->user()?->can($printable->permission()), 403);

        return [$printable, $printable->find($id)];
    }
}
