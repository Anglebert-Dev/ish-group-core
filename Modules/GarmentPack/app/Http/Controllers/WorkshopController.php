<?php

namespace Modules\GarmentPack\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\GarmentPack\Models\Bundle;
use Modules\GarmentPack\Models\CmtJob;
use Modules\GarmentPack\Models\Measurement;
use Modules\GarmentPack\Models\Worker;
use Modules\GarmentPack\Services\WorkshopService;
use Modules\Production\Models\WorkOrder;

class WorkshopController extends Controller
{
    public function __construct(protected WorkshopService $workshop)
    {
    }

    public function workers(Request $request): View
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('garmentpack::workshop.workers', [
            'workers' => Worker::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'payroll' => $this->workshop->payroll($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function storeWorker(Request $request): RedirectResponse
    {
        $worker = new Worker($this->validatedWorker($request));
        $worker->is_active = true;
        $worker->save();

        return back()->with('success', __('garmentpack::garment.worker_saved'));
    }

    public function updateWorker(Request $request, Worker $worker): RedirectResponse
    {
        $worker->fill($this->validatedWorker($request) + ['is_active' => $request->boolean('is_active')])->save();

        return back()->with('success', __('garmentpack::garment.worker_saved'));
    }

    public function markPaid(Request $request, Worker $worker): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        return back()->with('success', $this->workshop->markPaid($worker, $data['from'], $data['to'])['message']);
    }

    public function pieceWork(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'worker' => ['required', 'string'],
            'stage' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'work_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $worker = Worker::query()->where('uuid', $data['worker'])->where('is_active', true)->firstOrFail();

        return $this->respond($this->workshop->recordPieceWork($worker, $workOrder, (int) $data['stage'], (float) $data['quantity'], $data['work_date'] ?? null));
    }

    public function cmtIndex(): View
    {
        return view('garmentpack::workshop.cmt', [
            'jobs' => CmtJob::query()->with('workOrder.variant.item')->latest('id')->paginate(20),
        ]);
    }

    public function cmtSend(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'subcontractor' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'stage' => ['required', 'integer', 'min:1'],
            'sent_quantity' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'sent_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:sent_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->respond($this->workshop->sendCmt($workOrder, $data + ['rate' => (float) ($data['rate'] ?? 0)]));
    }

    public function cmtReturn(Request $request, CmtJob $job): RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);

        return $this->respond($this->workshop->returnCmt($job, (float) $request->input('quantity')));
    }

    public function bundles(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $request->validate(['size' => ['required', 'numeric', 'gt:0', 'max:10000']]);

        return $this->respond($this->workshop->makeBundles($workOrder, (float) $request->input('size')));
    }

    public function tickets(WorkOrder $workOrder): View
    {
        return view('garmentpack::workshop.tickets', [
            'workOrder' => $workOrder->load('variant.item'),
            'bundles' => Bundle::query()->where('work_order_id', $workOrder->id)->orderBy('number')->get(),
        ]);
    }

    public function storeMeasurement(Request $request, Customer $customer): RedirectResponse
    {
        $measurement = new Measurement($this->validatedMeasurement($request) + ['customer_id' => $customer->id]);
        $measurement->save();

        return back()->with('success', __('garmentpack::garment.measurement_saved'));
    }

    public function destroyMeasurement(Measurement $measurement): RedirectResponse
    {
        $measurement->delete();

        return back()->with('success', __('garmentpack::garment.measurement_deleted'));
    }

    private function respond(array $result): RedirectResponse
    {
        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function validatedWorker(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'line' => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function validatedMeasurement(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'taken_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'values' => ['required', 'array', 'max:40'],
            'values.*' => ['nullable', 'string', 'max:30'],
        ]);
        $data['values'] = array_filter($data['values'], fn ($v) => filled($v));

        return $data;
    }
}
