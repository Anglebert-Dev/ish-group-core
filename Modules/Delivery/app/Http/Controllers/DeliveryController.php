<?php

namespace Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Delivery\Models\DeliveryNote;
use Modules\Delivery\Services\DeliveryService;
use Modules\SalesOrders\Models\SalesOrder;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends Controller
{
    public function __construct(protected DeliveryService $deliveries)
    {
    }

    public function index(Request $request): View
    {
        return view('delivery::notes.index', [
            'notes' => DeliveryNote::query()->with('salesOrder.customer')
                ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(number) LIKE ?', ['%'.mb_strtolower($s).'%'])
                    ->orWhereRaw('LOWER(receiver_name) LIKE ?', ['%'.mb_strtolower($s).'%'])))
                ->latest('delivered_on')->latest('id')->paginate(20)->withQueryString(),
            'q' => $request->query('q'),
        ]);
    }

    public function create(SalesOrder $order): View|RedirectResponse
    {
        if (! $order->status->reservesStock()) {
            return redirect()->route('salesorders.orders.show', $order)->with('error', __('salesorders::orders.cannot_deliver'));
        }

        return view('delivery::notes.create', ['order' => $order->load(['customer', 'store', 'lines.variant.item'])]);
    }

    public function store(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'delivered_on' => ['required', 'date', 'before_or_equal:today'],
            'receiver_name' => ['required', 'string', 'max:120'],
            'receiver_phone' => ['nullable', 'string', 'max:40'],
            'signature' => ['nullable', 'string', 'max:300000', 'starts_with:data:image/png;base64,'],
            'vehicle' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->deliveries->create($order, $data['quantities'], collect($data)->except('quantities')->all());

        return $result['status']
            ? redirect()->route('delivery.notes.show', $result['data'])->with('success', $result['message'])
            : back()->withInput()->with('error', $result['message']);
    }

    public function show(DeliveryNote $note): View
    {
        return view('delivery::notes.show', ['note' => $note->load(['salesOrder.customer', 'salesOrder.store', 'lines.variant.item'])]);
    }

    public function pdf(DeliveryNote $note): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('delivery::notes.pdf', [
            'note' => $note->load(['salesOrder.customer', 'lines.variant.item']),
            'business' => $note->business,
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        audit(\App\Enums\ActivityLogAction::Downloaded, __('delivery::delivery.audit_pdf', ['number' => $note->number]), $note);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$note->number.'.pdf"',
        ]);
    }
}
