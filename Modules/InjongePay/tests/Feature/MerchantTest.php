<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Services\MerchantService;

class MerchantTest extends InjongePayTestCase
{
    private function merchants(): MerchantService
    {
        return app(MerchantService::class);
    }

    private function found(string $code, string $status = 'ACTIVE', string $name = 'Rhenium Group'): array
    {
        return ['responseCode' => '00', 'responseMessage' => 'Merchant found successfully.', 'data' => ['participantName' => 'INHILLS', 'merchant' => ['merchantId' => $code, 'businessName' => $name, 'businessOwnerName' => 'Owner Name', 'status' => $status]]];
    }

    public function test_a_new_wallet_is_registered_with_the_business_kyc_then_activated_by_sync(): void
    {
        $this->profile();
        $this->fakeBank([
            '/merchants/lookup' => Http::sequence()->push(['responseCode' => '01', 'responseMessage' => 'Merchant not found'])->push($this->found('55500001')),
            '/merchants/register' => Http::response(['status' => 'PENDING', 'message' => 'Merchant registration request accepted and is being processed', 'merchantId' => '55500001']),
        ]);
        $this->asOperator();

        $wallet = $this->merchants()->create($this->business, ['name' => 'Main till'])->fresh();

        $this->assertSame('55500001', $wallet->merchant_code);
        $this->assertSame(Wallet::BANK_PENDING, $wallet->bank_status);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/merchants/register') && $r['taxIdentificationNumber'] === '100001498'
            && $r['contactPhone'] === '+250788111222' && $r['businessName'] === 'Rhenium Group' && $r['channel'] === 'INHILLS');
        $this->assertFalse($wallet->acceptsPayments());

        $this->merchants()->sync($wallet);
        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->fresh()->bank_status);
        $this->assertTrue($wallet->fresh()->acceptsPayments());
    }

    public function test_missing_kyc_fails_before_anything_is_sent(): void
    {
        $this->fakeBank(['/merchants/lookup' => Http::response(['responseCode' => '01'])]);
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_NOT_REGISTERED]);

        $this->merchants()->register($wallet);

        $this->assertSame(Wallet::BANK_FAILED, $wallet->fresh()->bank_status);
        $this->assertStringContainsString('GTBank needs', $wallet->fresh()->bank_error);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/merchants/register'));
    }

    public function test_a_network_error_never_marks_a_wallet_failed(): void
    {
        $this->profile();
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_ACTIVE]);
        $this->fakeBank(['/merchants/lookup' => fn () => throw new ConnectionException('down')]);

        $this->merchants()->sync($wallet);

        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->fresh()->bank_status);
        $this->assertNotNull($wallet->fresh()->bank_error);
        $this->assertTrue($wallet->fresh()->is_enabled);
    }

    public function test_an_answer_about_another_merchant_code_is_ignored(): void
    {
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_PENDING]);
        $this->fakeBank(['/merchants/lookup' => Http::response($this->found('55500006'))]);

        $this->merchants()->sync($wallet);

        $this->assertSame(Wallet::BANK_PENDING, $wallet->fresh()->bank_status);
        $this->assertStringContainsString('55500006', $wallet->fresh()->bank_error);
    }

    public function test_sync_never_switches_the_platform_switch_back_on(): void
    {
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_PENDING, 'is_enabled' => false]);
        $this->fakeBank(['/merchants/lookup' => Http::response($this->found($wallet->merchant_code))]);

        $this->merchants()->sync($wallet);

        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->fresh()->bank_status);
        $this->assertFalse($wallet->fresh()->is_enabled);
        $this->assertFalse($wallet->fresh()->acceptsPayments());
    }

    public function test_gtbank_refusing_registration_records_why(): void
    {
        $this->profile();
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_NOT_REGISTERED]);
        $this->fakeBank([
            '/merchants/lookup' => Http::response(['responseCode' => '01']),
            '/merchants/register' => Http::response(['message' => 'Invalid phone'], 400),
        ]);

        $this->merchants()->register($wallet);

        $this->assertSame(Wallet::BANK_FAILED, $wallet->fresh()->bank_status);
        $this->assertSame('Invalid phone', $wallet->fresh()->bank_error);
    }

    public function test_a_details_change_waits_for_gtbank_to_apply_it(): void
    {
        $this->profile();
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_ACTIVE]);
        $this->fakeBank([
            '/merchants/update' => Http::response(['status' => 'PENDING', 'merchantId' => $wallet->merchant_code]),
            '/merchants/lookup' => Http::response($this->found($wallet->merchant_code)),
        ]);

        $this->merchants()->updateDetails($wallet, ['name' => 'Main till', 'kyc_contact_phone' => '+250788333444']);
        $this->assertNotNull($wallet->fresh()->bank_update_pending_at);
        $this->assertSame('+250788333444', $wallet->fresh()->kyc_contact_phone);

        $this->merchants()->sync($wallet->fresh());
        $this->assertNull($wallet->fresh()->bank_update_pending_at);
    }

    public function test_a_refused_change_is_not_saved(): void
    {
        $this->profile();
        $wallet = $this->wallet(overrides: ['bank_status' => Wallet::BANK_ACTIVE]);
        $this->fakeBank(['/merchants/update' => Http::response(['message' => 'Invalid phone'], 400)]);

        try {
            $this->merchants()->updateDetails($wallet, ['name' => 'Main till', 'kyc_contact_phone' => '+250788999999']);
            $this->fail('Refused change was saved');
        } catch (InjongePayException) {
        }

        $this->assertNull($wallet->fresh()->kyc_contact_phone);
    }

    public function test_only_an_empty_wallet_can_be_closed(): void
    {
        $wallet = $this->wallet();
        $this->fakeBank(['/merchants/close' => Http::response(['status' => 'PENDING', 'merchantId' => $wallet->merchant_code])]);
        app(Ledger::class)->post($wallet, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 100, 'payment', 'k1');

        try {
            $this->merchants()->close($wallet->fresh(), 'moving');
            $this->fail('Closed with a balance');
        } catch (InjongePayException) {
        }

        $empty = $this->wallet(overrides: ['name' => 'Empty']);
        $this->merchants()->close($empty, 'moving');
        $this->assertFalse($empty->fresh()->is_enabled);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/merchants/close') && $r['merchantId'] === $empty->merchant_code);
    }

    public function test_without_gtbank_wallets_are_active_at_once_test_mode(): void
    {
        $this->profile();
        $this->asOperator();

        $wallet = $this->merchants()->create($this->business, ['name' => 'Test till'])->fresh();

        $this->assertSame(Wallet::BANK_ACTIVE, $wallet->bank_status);
    }

    public function test_operator_screens_create_switch_off_and_show_calls(): void
    {
        $this->profile();
        $this->fakeBank([
            '/merchants/lookup' => Http::response(['responseCode' => '01']),
            '/merchants/register' => Http::response(['status' => 'PENDING', 'merchantId' => '55500001']),
        ]);
        $this->asOperator();

        $this->get(route('injongepay.platform.wallets.create'))->assertOk()->assertSee('Rhenium Group');
        $this->withConfirmedPassword()->post(route('injongepay.platform.wallets.store'), ['business' => $this->business->uuid, 'name' => 'Pump 1'])->assertRedirect();
        $wallet = Wallet::query()->withoutGlobalScopes()->where('name', 'Pump 1')->firstOrFail();

        $this->get(route('injongepay.platform.wallets.show', $wallet->uuid))->assertOk()->assertSee('/merchants/register')->assertSee('55500001');
        $this->get(route('injongepay.platform.wallets.index'))->assertOk()->assertSee('Pump 1');
        $this->get(route('injongepay.platform.bank-calls.index'))->assertOk()->assertSee('/merchants/lookup');

        $this->withConfirmedPassword()->put(route('injongepay.platform.wallets.toggle', $wallet->uuid), [])->assertSessionHasErrors('reason');
        $this->withConfirmedPassword()->put(route('injongepay.platform.wallets.toggle', $wallet->uuid), ['reason' => 'KYC review'])->assertRedirect();
        $this->assertFalse($wallet->fresh()->is_enabled);
    }

    public function test_business_admins_cannot_create_or_change_wallets(): void
    {
        $wallet = $this->wallet();
        $this->asOwner();

        $this->get(route('injongepay.platform.wallets.index'))->assertForbidden();
        $this->withConfirmedPassword()->post(route('injongepay.platform.wallets.store'), ['business' => $this->business->uuid, 'name' => 'Mine'])->assertForbidden();
        $this->withConfirmedPassword()->put(route('injongepay.platform.wallets.toggle', $wallet->uuid), ['reason' => 'x'])->assertForbidden();
        $this->post(route('injongepay.platform.wallets.sync', $wallet->uuid))->assertForbidden();
    }
}
