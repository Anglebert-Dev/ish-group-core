<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\InjongePay\Console\CheckCashoutsCommand;
use Modules\InjongePay\Jobs\SubmitCashout;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\TransactionPin;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\CashoutService;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Services\Security\Pins;

/** Money leaves once. Whatever is pressed, resent, retried or run twice. */
class DoubleSendTest extends InjongePayTestCase
{
    private Wallet $till;

    private SettlementAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->till = $this->wallet();
        app(Ledger::class)->post($this->till, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 10000000, 'payment', 'seed');
        $this->account = new SettlementAccount(['kind' => 'wallet', 'account' => '0788811607', 'holder_name' => 'Rose', 'bank_name' => 'MTN']);
        $this->account->forceFill(['business_id' => $this->business->id, 'status' => SettlementAccount::APPROVED, 'verified_names' => 'Rose', 'approved_at' => now()->subDays(3), 'usable_from' => now()->subDays(2)])->save();
        app(Pins::class)->set($this->owner, '48213');
        TransactionPin::query()->update(['set_at' => now()->subDays(3)]);
        $this->fakeBank(['/disbursement/transfer' => Http::response(['identifier' => 'X', 'status' => 'PENDING'])]);
        $this->business->refresh();
    }

    private function approved(): Approval
    {
        return Approval::query()->create(['business_id' => $this->business->id, 'wallet_id' => $this->till->id, 'operation' => 'cashout', 'amount' => 100000,
            'payload' => ['settlement_account_id' => $this->account->id, 'account_label' => 'x'], 'requested_by' => $this->owner->id, 'status' => Approval::APPROVED, 'checks' => []]);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + ['request_token' => (string) Str::uuid(), 'wallet' => $this->till->uuid, 'account' => $this->account->uuid, 'amount' => 1000, 'pin' => '48213'];
    }

    private function transfersSent(): int
    {
        return count(Http::recorded(fn (Request $r) => str_contains($r->url(), '/disbursement/transfer')));
    }

    public function test_the_form_sent_twice_is_one_cashout_and_the_same_page(): void
    {
        $this->asOwner();
        $form = $this->form(['amount' => 100]);
        $code = null;

        $this->post(route('injongepay.cashouts.store'), $form); // new device: asks for the code first
        Notification::assertSentTo($this->owner, \App\Notifications\BrandedNotification::class, function ($n) use (&$code) {
            preg_match('/\b(\d{6})\b/', json_encode($n), $m);
            $code = $m[1] ?? $code;

            return true;
        });

        $first = $this->post(route('injongepay.cashouts.store'), $form + ['code' => $code]);
        $second = $this->post(route('injongepay.cashouts.store'), $form + ['code' => $code]);

        $this->assertSame(1, Cashout::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, Approval::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, LedgerEntry::query()->withoutGlobalScopes()->where('source', 'cashout')->count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, $this->transfersSent());
    }

    public function test_a_post_without_its_token_is_refused(): void
    {
        $this->asOwner();
        $form = $this->form();
        unset($form['request_token']);

        $this->post(route('injongepay.cashouts.store'), $form)->assertSessionHasErrors('request_token');
        $this->assertSame(0, Approval::query()->withoutGlobalScopes()->count());
    }

    public function test_the_same_money_to_the_same_place_moments_later_is_stopped_even_from_another_tab(): void
    {
        $approvals = app(\Modules\InjongePay\Services\Approvals::class);
        $this->approved();
        $request = fn () => (new \ReflectionMethod($approvals, 'refuseRepeat'))->invoke($approvals, $this->till, 'cashout', 100000, ['settlement_account_id' => $this->account->id]);

        try {
            $request();
            $this->fail('It should have been stopped.');
        } catch (\Modules\InjongePay\Exceptions\InjongePayException $e) {
            $this->assertStringContainsString('twice', $e->getMessage());
        }

        // Another amount or another account is another payment.
        (new \ReflectionMethod($approvals, 'refuseRepeat'))->invoke($approvals, $this->till, 'cashout', 200000, ['settlement_account_id' => $this->account->id]);
        (new \ReflectionMethod($approvals, 'refuseRepeat'))->invoke($approvals, $this->till, 'cashout', 100000, ['settlement_account_id' => 999]);

        $this->travel(3)->minutes();
        $request();
        $this->assertTrue(true);
    }

    public function test_one_approval_run_twice_is_one_debit(): void
    {
        $approval = $this->approved();

        $first = app(CashoutService::class)->start($approval);
        $second = app(CashoutService::class)->start($approval->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(10000000 - 100000, $this->till->fresh()->available_balance);
    }

    public function test_the_job_retried_or_the_safety_net_running_too_sends_once(): void
    {
        $cashout = app(CashoutService::class)->start($this->approved());

        (new SubmitCashout($cashout->id))->handle(app(CashoutService::class));
        (new SubmitCashout($cashout->id))->handle(app(CashoutService::class));
        Cashout::query()->withoutGlobalScopes()->whereKey($cashout->id)->update(['created_at' => now()->subMinutes(5)]);
        $this->artisan(CheckCashoutsCommand::class);

        $this->assertSame(1, $this->transfersSent());
    }

    public function test_a_transfer_that_timed_out_is_never_sent_again(): void
    {
        Http::fake(['*/disbursement/transfer*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $cashout = app(CashoutService::class)->start($this->approved());
        $this->assertSame(Cashout::UNKNOWN, $cashout->fresh()->status);

        foreach (range(1, 5) as $i) {
            $this->travel(1)->hours();
            app(CashoutService::class)->check($cashout->fresh());
        }

        $this->assertSame(1, BankCall::query()->where('endpoint', '/disbursement/transfer')->count());
    }

    public function test_money_comes_back_once(): void
    {
        $cashout = app(CashoutService::class)->start($this->approved());

        app(CashoutService::class)->resolve($cashout->fresh(), 'failed', 'GTBank confirmed it never left');

        try {
            app(CashoutService::class)->resolve($cashout->fresh(), 'failed', 'Pressed a second time by mistake');
        } catch (\Modules\InjongePay\Exceptions\InjongePayException) {
        }

        $this->assertSame(10000000, $this->till->fresh()->available_balance);
        $this->assertSame(1, LedgerEntry::query()->withoutGlobalScopes()->where('source', 'cashout_reversal')->count());
    }

    public function test_a_failed_request_leaves_its_form_usable(): void
    {
        $token = (string) Str::uuid();
        $approval = $this->approved();
        $approval->forceFill(['request_token' => $token])->save();

        app(\Modules\InjongePay\Services\Approvals::class)->fail($approval, 'Account off');

        $this->assertNull($approval->fresh()->request_token);
    }

    public function test_the_database_itself_refuses_a_second_cashout_on_one_approval(): void
    {
        $cashout = app(CashoutService::class)->start($this->approved());

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('ijp_cashouts')->insert(['uuid' => (string) Str::uuid(), 'reference' => 'CSHDUP', 'business_id' => $cashout->business_id, 'wallet_id' => $cashout->wallet_id, 'approval_id' => $cashout->approval_id, 'amount' => 1, 'account' => 'x', 'account_holder' => 'x', 'status' => Cashout::QUEUED]);
    }

    public function test_the_database_itself_refuses_a_token_used_twice(): void
    {
        $token = (string) Str::uuid();
        $this->approved()->forceFill(['request_token' => $token])->save();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->approved()->forceFill(['request_token' => $token])->save();
    }
}
