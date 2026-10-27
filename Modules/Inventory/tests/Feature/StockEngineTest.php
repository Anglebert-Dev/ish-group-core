<?php

namespace Modules\Inventory\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use InvalidArgumentException;
use LogicException;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Movement;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Services\StockService;

class StockEngineTest extends InventoryTestCase
{
    public function test_receipts_and_issues_are_logged_with_running_balance(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $store = $this->defaultStore($business);
        $stock = app(StockService::class);

        $stock->receive($variant, $store, 50, 'Delivery', 1200);
        $stock->issue($variant, $store, 12.5, 'Damaged');

        $this->assertSame(37.5, $stock->onHand($variant));
        $this->assertSame([50.0, -12.5], Movement::query()->orderBy('id')->pluck('quantity')->all());
        $this->assertSame([50.0, 37.5], Movement::query()->orderBy('id')->pluck('balance_after')->all());

        // On hand is always the sum of the movement log.
        $this->assertSame((float) Movement::query()->sum('quantity'), (float) StockLevel::query()->sum('quantity'));
    }

    public function test_stock_can_never_go_below_zero(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $store = $this->defaultStore($business);
        $stock = app(StockService::class);
        $stock->receive($variant, $store, 5);

        try {
            $stock->issue($variant, $store, 6, 'Too many');
            $this->fail('Issue beyond stock was accepted.');
        } catch (InsufficientStock $e) {
            $this->assertSame('Not enough School shirt in Main store: 5 available, 6 needed.', $e->getMessage());
        }

        $this->assertSame(5.0, $stock->onHand($variant));
        $this->assertSame(1, Movement::query()->count());
    }

    public function test_two_lines_of_the_same_item_cannot_oversell(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $store = $this->defaultStore($business);
        $stock = app(StockService::class);
        $stock->receive($variant, $store, 8);

        $this->expectException(InsufficientStock::class);

        try {
            $stock->record(MovementType::Sale, [
                ['variant' => $variant, 'store' => $store, 'quantity' => -5],
                ['variant' => $variant, 'store' => $store, 'quantity' => -5],
            ], referenceLabel: 'INV-1');
        } finally {
            $this->assertSame(8.0, $stock->onHand($variant), 'nothing saved');
            $this->assertSame(1, Movement::query()->count());
        }
    }

    public function test_a_multi_line_movement_is_all_or_nothing(): void
    {
        [$business] = $this->makeBusiness();
        [, [$small, $large]] = $this->item($business, ['S', 'L']);
        $store = $this->defaultStore($business);
        $stock = app(StockService::class);
        $stock->receive($small, $store, 10);

        try {
            $stock->record(MovementType::Sale, [
                ['variant' => $small, 'store' => $store, 'quantity' => -4],
                ['variant' => $large, 'store' => $store, 'quantity' => -1],
            ]);
        } catch (InsufficientStock) {
        }

        $this->assertSame(10.0, $stock->onHand($small));
        $this->assertSame(0.0, $stock->onHand($large));
    }

    public function test_correction_sets_the_counted_quantity(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $store = $this->defaultStore($business);
        $stock = app(StockService::class);
        $stock->receive($variant, $store, 20);

        $movements = $stock->adjustTo($variant, $store, 17, 'Stock count');

        $this->assertSame(-3.0, $movements->first()->quantity);
        $this->assertSame(MovementType::Adjustment, $movements->first()->type);
        $this->assertSame(17.0, $stock->onHand($variant));
        $this->assertTrue($stock->adjustTo($variant, $store, 17, 'Again')->isEmpty(), 'no movement when the count matches');
    }

    public function test_transfer_moves_stock_between_stores(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $main = $this->defaultStore($business);
        $shop = $this->store($business, 'Shop');
        $stock = app(StockService::class);
        $stock->receive($variant, $main, 10);

        $stock->transfer($variant, $main, $shop, 4, 'For the shop');

        $this->assertSame(6.0, $stock->onHand($variant, $main));
        $this->assertSame(4.0, $stock->onHand($variant, $shop));
        $this->assertSame(10.0, $stock->onHand($variant));
        $this->assertSame(1, Movement::query()->where('type', 'transfer_out')->distinct()->count('batch'));
        $this->assertSame(Movement::query()->where('type', 'transfer_out')->value('batch'), Movement::query()->where('type', 'transfer_in')->value('batch'));

        $this->expectException(InsufficientStock::class);
        $stock->transfer($variant, $shop, $main, 5);
    }

    public function test_movements_cannot_be_changed_or_deleted(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business);
        $movement = app(StockService::class)->receive($variant, $this->defaultStore($business), 3)->first();

        $this->expectException(LogicException::class);
        $movement->forceFill(['quantity' => 300])->save();
    }

    public function test_stock_changes_are_audited_for_the_business(): void
    {
        [$business] = $this->makeBusiness();
        [, [$variant]] = $this->item($business, ['M']);
        app(StockService::class)->receive($variant, $this->defaultStore($business), 50);

        $log = ActivityLog::query()->where('business_id', $business->id)->where('action', 'stock_receipt')->firstOrFail();
        $this->assertSame('Received: 50 pcs of School shirt (M), Main store', $log->description);
        $this->assertSame('Stock received', $log->actionLabel());
    }

    public function test_lines_must_belong_to_one_business(): void
    {
        [$alpha] = $this->makeBusiness();
        [$bravo] = $this->makeBusiness();
        [, [$variant]] = $this->item($alpha);

        $this->expectException(InvalidArgumentException::class);
        app(StockService::class)->receive($variant, $this->defaultStore($bravo), 1);
    }
}
