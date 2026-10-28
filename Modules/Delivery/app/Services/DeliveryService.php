<?php

namespace Modules\Delivery\Services;

use App\Services\BaseService;
use App\Support\Documents\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Modules\Delivery\Models\DeliveryNote;
use Modules\Delivery\Models\DeliveryNoteLine;
use Modules\SalesOrders\Models\SalesOrder;
use Modules\SalesOrders\Services\OrderService;
use RuntimeException;

class DeliveryService extends BaseService
{
    public function __construct(
        protected DocumentNumber $numbers,
        protected OrderService $orders,
    ) {
    }

    /**
     * Deliver part (or all) of an order: [order line id => quantity].
     */
    public function create(SalesOrder $order, array $quantities, array $data): array
    {
        $quantities = array_filter(array_map(fn ($q) => round((float) $q, 3), $quantities), fn ($q) => $q > 0);

        try {
            $note = DB::transaction(function () use ($order, $quantities, $data) {
                $note = new DeliveryNote($data);
                $note->business_id = $order->business_id;
                $note->sales_order_id = $order->id;
                $note->number = $this->numbers->next('delivery_note', (string) config('delivery.prefix', 'DN'));
                $note->save();

                $result = $this->orders->deliverLines($order, $quantities, $note, $note->number);

                if (! $result['status']) {
                    throw new RuntimeException($result['message']);
                }

                foreach ($order->lines as $line) {
                    if (($quantities[$line->id] ?? 0) > 0) {
                        $row = new DeliveryNoteLine(['order_line_id' => $line->id, 'variant_id' => $line->variant_id, 'quantity' => $quantities[$line->id]]);
                        $row->business_id = $order->business_id;
                        $row->delivery_note_id = $note->id;
                        $row->save();
                    }
                }

                return $note;
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($note, __('delivery::delivery.created', ['number' => $note->number]));
    }
}
