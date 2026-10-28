<?php

namespace Modules\GarmentPack\Services;

use App\Enums\ActivityLogAction;
use App\Services\BaseService;
use App\Support\Documents\DocumentNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\GarmentPack\Models\Bundle;
use Modules\GarmentPack\Models\CmtJob;
use Modules\GarmentPack\Models\PieceWork;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Models\Worker;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Production\Models\WorkOrder;
use Modules\Production\Services\WorkOrderService;
use RuntimeException;

/**
 * Piece-rate work, subcontracting (CMT) and bundles on work orders.
 * Every count goes through WorkOrderService, so the stage rules apply.
 */
class WorkshopService extends BaseService
{
    public function __construct(
        protected WorkOrderService $workOrders,
        protected DocumentNumber $numbers,
    ) {
    }

    /**
     * A worker did $quantity pieces at a stage: counted on the work order and paid at the style's rate.
     */
    public function recordPieceWork(Worker $worker, WorkOrder $workOrder, int $stage, float $quantity, ?string $date = null): array
    {
        $style = Style::query()->where('item_id', $workOrder->variant?->item_id)->first();
        $stageName = $workOrder->stages[$stage] ?? null;

        if (! $stageName || $quantity <= 0) {
            return $this->failure(__('garmentpack::garment.piece_work_invalid'));
        }

        try {
            $work = DB::transaction(function () use ($worker, $workOrder, $stage, $stageName, $quantity, $date, $style) {
                $result = $this->workOrders->record($workOrder, $stage, $quantity, $date, __('garmentpack::garment.piece_work_note', ['worker' => $worker->name]));

                if (! $result['status']) {
                    throw new RuntimeException($result['message']);
                }

                $rate = $style?->pieceRate($stageName) ?? 0;
                $work = new PieceWork([
                    'worker_id' => $worker->id,
                    'work_order_id' => $workOrder->id,
                    'stage' => $stage,
                    'stage_name' => $stageName,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'amount' => round($quantity * $rate, 2),
                    'work_date' => $date ?: now()->toDateString(),
                ]);
                $work->business_id = $workOrder->business_id;
                $work->save();

                return $work;
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($work, __('garmentpack::garment.piece_work_saved', ['amount' => money($work->amount)]));
    }

    /**
     * Pieces and pay per worker for a period.
     *
     * @return Collection<int, array{worker: Worker, pieces: float, amount: float, unpaid: float}>
     */
    public function payroll(string $from, string $to): Collection
    {
        $rows = PieceWork::query()
            ->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)
            ->selectRaw('worker_id, SUM(quantity) as pieces, SUM(amount) as amount, SUM(CASE WHEN paid_at IS NULL THEN amount ELSE 0 END) as unpaid')
            ->groupBy('worker_id')->get()->keyBy('worker_id');

        return Worker::query()->whereIn('id', $rows->keys())->orderBy('name')->get()->map(fn (Worker $worker) => [
            'worker' => $worker,
            'pieces' => (float) $rows[$worker->id]->pieces,
            'amount' => (float) $rows[$worker->id]->amount,
            'unpaid' => (float) $rows[$worker->id]->unpaid,
        ]);
    }

    public function markPaid(Worker $worker, string $from, string $to): array
    {
        $amount = (float) PieceWork::query()->where('worker_id', $worker->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->whereNull('paid_at')->sum('amount');
        PieceWork::query()->where('worker_id', $worker->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->whereNull('paid_at')
            ->get()->each(fn (PieceWork $work) => $work->forceFill(['paid_at' => now()])->save());

        audit(ActivityLogAction::Updated, __('garmentpack::garment.audit_paid', ['worker' => $worker->name, 'amount' => money($amount), 'from' => $from, 'to' => $to]), $worker);

        return $this->success($worker, __('garmentpack::garment.paid_saved', ['amount' => money($amount)]));
    }

    /**
     * Send pieces out for a stage. Never more than are waiting for that stage.
     */
    public function sendCmt(WorkOrder $workOrder, array $data): array
    {
        $stage = (int) $data['stage'];
        $quantity = round((float) $data['sent_quantity'], 3);

        if ($stage < 1 || $stage > $workOrder->lastStage()) {
            return $this->failure(__('garmentpack::garment.cmt_stage_invalid'));
        }

        $outstanding = (float) CmtJob::query()->where('work_order_id', $workOrder->id)->where('stage', $stage)
            ->selectRaw('COALESCE(SUM(sent_quantity - returned_quantity), 0) as q')->value('q');
        $waiting = $workOrder->count($stage - 1) - $workOrder->count($stage) - $outstanding;

        if ($quantity <= 0 || $quantity > $waiting + 0.0005) {
            return $this->failure(__('garmentpack::garment.cmt_too_many', ['max' => InsufficientStock::format(max(0, $waiting)), 'stage' => $workOrder->stages[$stage]]));
        }

        $job = new CmtJob($data + ['stage_name' => $workOrder->stages[$stage]]);
        $job->business_id = $workOrder->business_id;
        $job->work_order_id = $workOrder->id;
        $job->number = $this->numbers->next('cmt_job', 'CMT');
        $job->status = 'sent';
        $job->save();

        return $this->success($job, __('garmentpack::garment.cmt_sent', ['number' => $job->number]));
    }

    /**
     * Pieces came back done: counted at the job's stage on the work order.
     */
    public function returnCmt(CmtJob $job, float $quantity): array
    {
        $quantity = round($quantity, 3);

        if ($quantity <= 0 || $quantity > $job->outstanding() + 0.0005) {
            return $this->failure(__('garmentpack::garment.cmt_return_too_many', ['max' => InsufficientStock::format($job->outstanding())]));
        }

        try {
            DB::transaction(function () use ($job, $quantity) {
                $result = $this->workOrders->record($job->workOrder, $job->stage, $quantity, null, __('garmentpack::garment.cmt_note', ['number' => $job->number, 'name' => $job->subcontractor]));

                if (! $result['status']) {
                    throw new RuntimeException($result['message']);
                }

                $returned = round($job->returned_quantity + $quantity, 3);
                $job->forceFill([
                    'returned_quantity' => $returned,
                    'status' => $returned >= $job->sent_quantity - 0.0005 ? 'returned' : 'partly_returned',
                ])->save();
            });
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success($job, __('garmentpack::garment.cmt_returned'));
    }

    /**
     * Split the cut pieces not yet bundled into numbered bundles of $size.
     */
    public function makeBundles(WorkOrder $workOrder, float $size): array
    {
        $bundled = (float) Bundle::query()->where('work_order_id', $workOrder->id)->sum('quantity');
        $left = round($workOrder->count(0) - $bundled, 3);

        if ($size <= 0 || $left <= 0) {
            return $this->failure(__('garmentpack::garment.bundles_nothing'));
        }

        $number = (int) Bundle::query()->where('work_order_id', $workOrder->id)->max('number');
        $made = 0;

        while ($left > 0.0005) {
            $quantity = min($size, $left);
            Bundle::query()->create([
                'business_id' => $workOrder->business_id,
                'work_order_id' => $workOrder->id,
                'number' => ++$number,
                'quantity' => $quantity,
            ]);
            $left = round($left - $quantity, 3);
            $made++;
        }

        return $this->success(null, __('garmentpack::garment.bundles_made', ['count' => $made]));
    }
}
