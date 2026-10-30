<?php

namespace Modules\Savings\Tests\Feature;

use App\Models\Business\Business;
use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Support\Tenancy\CurrentBusiness;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Services\Balances;
use Modules\Savings\Services\Ledger;
use Modules\Savings\Services\Obligations;
use Modules\Savings\Services\Operations;
use Modules\Savings\Services\Planning;
use Tests\TestCase;

abstract class SavingsTestCase extends TestCase
{
    protected Business $business;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Savings');
        [$this->business, $this->owner] = $this->makeBusiness();
        $this->actingAs($this->owner, 'staff');
        app(CurrentBusiness::class)->set($this->business);
    }

    protected function account(string $name = 'Bank', int $opening = 0, array $overrides = []): Account
    {
        $account = new Account($overrides + [
            'name' => $name, 'type' => 'bank', 'currency' => 'RWF', 'opening_date' => now()->subYear()->toDateString(),
            'opening_balance' => $opening, 'is_liquid' => true, 'status' => 'active',
        ]);
        $account->save();

        return $account;
    }

    protected function category(string $name = 'Food', string $kind = 'expense', ?Category $parent = null): Category
    {
        $category = new Category(['name' => $name, 'kind' => $kind, 'parent_id' => $parent?->id]);
        $category->save();

        return $category;
    }

    protected function balance(Account $account, ?string $asOf = null): int
    {
        return app(Balances::class)->balance($account->fresh(), $asOf);
    }

    protected function ops(): Operations
    {
        return app(Operations::class);
    }

    protected function ledger(): Ledger
    {
        return app(Ledger::class);
    }

    protected function obligations(): Obligations
    {
        return app(Obligations::class);
    }

    protected function planning(): Planning
    {
        return app(Planning::class);
    }

    protected function day(int $offset = 0): string
    {
        return now()->addDays($offset)->toDateString();
    }
}
