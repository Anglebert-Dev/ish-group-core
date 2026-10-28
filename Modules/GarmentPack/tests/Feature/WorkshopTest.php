<?php

namespace Modules\GarmentPack\Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer\Customer;
use App\Services\Module\ModuleManager;
use Modules\GarmentPack\Models\Bundle;
use Modules\GarmentPack\Models\CmtJob;
use Modules\GarmentPack\Models\PieceWork;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Models\Worker;
use Modules\Inventory\Tests\Feature\InventoryTestCase;
use Modules\Production\Models\WorkOrder;

class WorkshopTest extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Production');
        app(ModuleManager::class)->install('GarmentPack');
    }

    private function scenario(): array
    {
        $owner = $this->actingAsStaff();
        [$shirt, [$medium]] = $this->item($owner->business, ['M']);
        $store = $this->defaultStore($owner->business);
        $this->post(route('garmentpack.styles.store'), [
            'code' => 'SH-01', 'name' => 'Shirt', 'item' => $shirt->uuid,
            'piece_rates' => ['Cutting' => 50, 'Sewing' => 300, 'Finishing' => 100, 'Packing' => ''],
        ])->assertSessionHas('success');
        $this->assertEquals(['Cutting' => 50, 'Sewing' => 300, 'Finishing' => 100], Style::query()->first()->piece_rates);

        $this->post(route('production.work-orders.store'), ['variant' => $medium->uuid, 'store' => $store->uuid, 'quantity' => 100]);
        $this->post(route('garmentpack.workers.store'), ['name' => 'Aline', 'line' => 'A']);

        return [WorkOrder::query()->firstOrFail(), Worker::query()->firstOrFail(), $owner];
    }

    private function piece(WorkOrder $wo, Worker $worker, int $stage, float $quantity)
    {
        return $this->post(route('garmentpack.work-orders.piece-work', $wo), ['worker' => $worker->uuid, 'stage' => $stage, 'quantity' => $quantity, 'work_date' => now()->toDateString()]);
    }

    public function test_piece_work_counts_and_pays_and_follows_stage_rules(): void
    {
        [$wo, $aline] = $this->scenario();

        $this->piece($wo, $aline, 1, 10)->assertSessionHas('error', 'Sewing cannot be more than Cutting (0).');
        $this->assertSame(0, PieceWork::query()->count(), 'refused counts are not paid');

        $this->piece($wo, $aline, 0, 100)->assertSessionHas('success');
        $this->piece($wo, $aline, 1, 40)->assertSessionHas('success', 'Piece work recorded (RWF 12,000).');
        $this->assertSame(40.0, $wo->fresh()->count(1));

        $this->get(route('garmentpack.workers.index'))->assertOk()->assertSee('Aline')->assertSee('RWF 17,000');
        $this->withConfirmedPassword()->post(route('garmentpack.workers.paid', $aline), ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()])
            ->assertSessionHas('success', 'RWF 17,000 marked as paid.');
        $this->assertSame(0, PieceWork::query()->whereNull('paid_at')->count());
    }

    public function test_cmt_sends_waiting_pieces_and_counts_them_when_back(): void
    {
        [$wo] = $this->scenario();
        $this->post(route('production.work-orders.record', $wo), ['stage' => 0, 'quantity' => 60]);
        $send = fn ($qty) => $this->post(route('garmentpack.work-orders.cmt', $wo), ['subcontractor' => 'Jean Tailor', 'stage' => 1, 'sent_quantity' => $qty, 'rate' => 250, 'sent_on' => now()->toDateString()]);

        $send(61)->assertSessionHas('error', 'Only 60 pieces are waiting for Sewing.');
        $send(50)->assertSessionHas('success');
        $send(11)->assertSessionHas('error', 'Only 10 pieces are waiting for Sewing.');

        $job = CmtJob::query()->firstOrFail();
        $this->post(route('garmentpack.cmt.return', $job), ['quantity' => 51])->assertSessionHas('error');
        $this->post(route('garmentpack.cmt.return', $job), ['quantity' => 30])->assertSessionHas('success');

        $this->assertSame(30.0, $wo->fresh()->count(1));
        $this->assertSame(7500.0, $job->fresh()->owed());
        $this->get(route('garmentpack.cmt.index'))->assertOk()->assertSee('Jean Tailor');
    }

    public function test_bundles_split_cut_pieces_and_print_tickets(): void
    {
        [$wo] = $this->scenario();
        $this->post(route('production.work-orders.record', $wo), ['stage' => 0, 'quantity' => 45]);

        $this->post(route('garmentpack.work-orders.bundles', $wo), ['size' => 20])->assertSessionHas('success', '3 bundles made.');
        $this->assertSame([20.0, 20.0, 5.0], Bundle::query()->orderBy('number')->pluck('quantity')->all());
        $this->post(route('garmentpack.work-orders.bundles', $wo), ['size' => 20])->assertSessionHas('error');

        $this->get(route('garmentpack.work-orders.tickets', $wo))->assertOk()->assertSee('#3');
        $this->get(route('production.work-orders.show', $wo))->assertOk()->assertSee(__('garmentpack::garment.piece_work'));
    }

    public function test_customer_measurements(): void
    {
        $owner = $this->actingAsStaff();
        $customer = new Customer(['type' => 'person', 'name' => 'Grace']);
        $customer->business_id = $owner->business_id;
        $customer->status = CustomerStatus::Active;
        $customer->save();

        $this->post(route('garmentpack.measurements.store', $customer), [
            'name' => 'Wedding dress', 'values' => ['Chest' => '92', 'Waist' => '70', 'Hips' => ''],
        ])->assertSessionHas('success');

        $this->get(route('customers.show', $customer))->assertOk()->assertSee('Wedding dress')->assertSee('Waist:');
    }
}
