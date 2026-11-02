<?php

namespace Modules\Delivery\Printing;

use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Delivery\Models\DeliveryNote;
use Modules\Inventory\Exceptions\InsufficientStock;

/**
 * A delivery note, with who received it and their signature.
 */
class DeliveryNotePrintable extends Printable
{
    public function label(): string
    {
        return __('delivery::delivery.pdf_title');
    }

    public function permission(): string
    {
        return 'read delivery.note';
    }

    public function find(string $key): Model
    {
        return DeliveryNote::query()->where('uuid', $key)->with(['salesOrder.customer', 'lines.variant.item'])->firstOrFail();
    }

    /**
     * @param  DeliveryNote  $note
     */
    public function document(Model $note): Document
    {
        return Document::make('delivery_note', __('delivery::delivery.pdf_title'))
            ->number($note->number)
            ->meta(__('delivery::delivery.pdf_delivered_on'), $note->delivered_on?->format('d M Y'))
            ->meta(__('delivery::delivery.order'), $note->salesOrder?->number)
            ->meta(__('delivery::delivery.pdf_vehicle'), $note->vehicle)
            ->customer(__('delivery::delivery.pdf_deliver_to'), $note->salesOrder?->customer, delivery: true)
            ->table([['#'], [__('inventory::inventory.line_item')], [__('inventory::inventory.line_quantity'), 'right']],
                $note->lines->values()->map(fn ($line, $i) => [
                    $i + 1,
                    $line->variant?->displayName(),
                    trim(InsufficientStock::format($line->quantity).' '.$line->variant?->item?->unit),
                ])->all())
            ->section(__('delivery::delivery.pdf_received'), [
                $note->receiver_name,
                $note->receiver_phone,
            ], $note->signature)
            ->notes($note->notes)
            ->withSignature();
    }
}
