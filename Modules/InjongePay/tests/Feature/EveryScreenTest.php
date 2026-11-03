<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\StatementCheck;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Services\FeeSweep;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePayConnect\Models\ApiCall;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Services\Credentials;

/**
 * Opens every Injonge Pay and Integrations page, with data on it, as the
 * business and as Inhills. Every page must open for whoever it is for, refuse
 * whoever it isn't, and never fail.
 */
class EveryScreenTest extends InjongePayTestCase
{
    private array $params = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        app(ModuleManager::class)->install('InjongePayConnect');
        $this->seedEverything();
    }

    private function seedEverything(): void
    {
        $this->profile();
        $wallet = $this->wallet(overrides: ['name' => 'Main till']);
        $other = $this->wallet(overrides: ['name' => 'Second till']);
        $payment = app(PaymentIntake::class)->receive(['transactionId' => 'MPay_1', 'merchantId' => $wallet->merchant_code, 'amount' => '10000.00', 'status' => 'SUCCESS', 'debtorName' => 'JOHN'], Payment::VIA_BANK_PULL)[1];
        app(PaymentIntake::class)->receive(['transactionId' => 'MPay_X', 'merchantId' => '55599999', 'amount' => '700.00', 'status' => 'SUCCESS'], Payment::VIA_WEBHOOK);
        app(FeeSweep::class)->run();

        $account = new SettlementAccount(['kind' => 'wallet', 'account' => '0788811607', 'holder_name' => 'Rose']);
        $account->forceFill(['business_id' => $this->business->id, 'status' => SettlementAccount::APPROVED, 'usable_from' => now()])->save();
        $approval = Approval::query()->create(['business_id' => $this->business->id, 'wallet_id' => $wallet->id, 'operation' => 'cashout', 'amount' => 100000,
            'payload' => ['settlement_account_id' => $account->id, 'account_label' => $account->label()], 'requested_by' => $this->owner->id, 'status' => Approval::AWAITING, 'expires_at' => now()->addDay()]);
        $cashout = Cashout::query()->create(['reference' => 'CSH20261104000001', 'business_id' => $this->business->id, 'wallet_id' => $wallet->id, 'amount' => 100000,
            'account' => '0788811607', 'account_holder' => 'Rose', 'status' => Cashout::NEEDS_REVIEW]);
        Transfer::query()->create(['reference' => 'TRF20261104000001', 'business_id' => $this->business->id, 'from_wallet_id' => $wallet->id, 'to_wallet_id' => $other->id, 'amount' => 100]);
        ReconciliationItem::query()->create(['kind' => 'missing_at_bank', 'item_key' => 'missing_at_bank:X', 'business_id' => $this->business->id, 'wallet_id' => $wallet->id, 'amount' => 700]);
        $check = StatementCheck::query()->create(['period_from' => today(), 'period_to' => today(), 'file_name' => 's.csv', 'summary' => [
            'columns' => [], 'bank_credits' => 0, 'bank_credit_lines' => 0, 'our_payments' => 0, 'our_payment_count' => 0, 'bank_debits' => 0, 'bank_debit_lines' => 0,
            'our_cashouts' => 0, 'our_cashout_count' => 0, 'bank_closing' => null, 'books_hold' => 0], 'unmatched' => [], 'agrees' => true]);
        $bankCall = BankCall::query()->create(['direction' => 'outbound', 'method' => 'GET', 'endpoint' => '/merchants/lookup', 'outcome' => 'ok', 'reference' => $wallet->merchant_code]);
        $integrator = Integrator::query()->create(['name' => 'Injonge EBM', 'profile' => 'pos-ebm-v1', 'is_enabled' => true]);
        [$connection] = app(Credentials::class)->create($integrator, $this->business);
        $apiCall = ApiCall::query()->create(['connection_id' => $connection->id, 'business_id' => $this->business->id, 'method' => 'GET', 'path' => 'api/partner/v1/institution', 'status' => 200]);
        UnallocatedPayment::query()->firstOrFail();

        $this->params = [
            'wallet' => $wallet->uuid, 'payment' => $payment->uuid, 'cashout' => $cashout->uuid, 'approval' => $approval->uuid,
            'account' => $account->uuid, 'business' => $this->business->uuid, 'check' => $check->uuid, 'integrator' => $integrator->uuid,
            'connection' => $connection->uuid, 'format' => 'excel',
        ];
        $this->params['call'] = fn (string $route) => str_starts_with($route, 'injongepayconnect.') ? $apiCall->id : $bankCall->uuid;
    }

    /** @return array<string, string> route name => url, for every page (GET, not the API) of both modules */
    private function pages(string $side): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! in_array('GET', $route->methods(), true) || ! preg_match('/^injongepay(connect)?\./', $name) || str_starts_with($route->uri(), 'api/')
                || str_contains($name, '.webhook') || str_contains($name, '.v1.') || str_contains($name, 'legacy')) {
                continue;
            }

            $isPlatform = str_contains($name, '.platform.');
            if (($side === 'platform') !== $isPlatform) {
                continue;
            }

            $params = [];
            foreach ($route->parameterNames() as $param) {
                $value = $this->params[$param] ?? null;
                $params[$param] = $value instanceof \Closure ? $value($name) : $value;
            }

            $pages[$name] = route($name, $params);
        }

        ksort($pages);

        return $pages;
    }

    public function test_every_business_page_opens_for_the_business(): void
    {
        $this->asOwner();
        $pages = $this->pages('business');
        $this->assertGreaterThan(15, count($pages));

        foreach ($pages as $name => $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertContains($status, [200, 302], "{$name} ({$url}) answered {$status}");
        }

        $this->get(route('dashboard'))->assertOk()->assertSee('Main till');
    }

    public function test_every_inhills_page_opens_for_inhills_and_is_refused_to_the_business(): void
    {
        $this->asOperator();
        $pages = $this->pages('platform');
        $this->assertGreaterThan(15, count($pages));

        foreach ($pages as $name => $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertContains($status, [200, 302], "{$name} ({$url}) answered {$status} for Inhills");
        }

        $this->get(route('dashboard'))->assertOk()->assertSee(__('injongepay::injongepay.dash_needs_you'));

        $this->flushSession();
        $this->asOwner();
        foreach ($pages as $name => $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertContains($status, [302, 403, 404], "{$name} ({$url}) answered {$status} for a business");
        }
    }
}
