<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\InjongePay\Console\SyncMerchantsCommand;
use Modules\InjongePay\Jobs\RegisterMerchant;
use Modules\InjongePay\Models\BankTransaction;
use Modules\InjongePay\Models\DailyClose;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BankMirror;
use Modules\InjongePay\Services\Reconciliation;

/** A business with hundreds of tills: created from one CSV, read from GTBank in one call. */
class ManyWalletsTest extends InjongePayTestCase
{
    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('tills.csv', $content);
    }

    private function row(string $id, string $code, string $amount = '10000.00'): array
    {
        return ['transactionId' => $id, 'merchantId' => $code, 'amount' => $amount, 'status' => 'SUCCESS', 'valueDate' => '2026-11-02', 'createdAt' => '2026-11-02T09:55:19'];
    }

    public function test_inhills_creates_many_merchant_codes_from_a_csv_and_gtbank_gets_them_spread_out(): void
    {
        Queue::fake();
        $this->asOperator();
        $this->get(route('injongepay.platform.wallets.import'))->assertOk();
        $this->get(route('injongepay.platform.wallets.import.template'))->assertOk()->assertDownload('merchant-codes-template.csv');

        $lines = "name;owner;tin;phone;email\n";
        foreach (range(1, 40) as $i) {
            $lines .= "Till {$i};Jane Doe;100001498;0788 111 222;till{$i}@example.com\n";
        }

        $this->withConfirmedPassword()->post(route('injongepay.platform.wallets.import.store'), ['business' => $this->business->uuid, 'file' => $this->csv("\xEF\xBB\xBF".$lines)])
            ->assertRedirect(route('injongepay.platform.wallets.index', ['business' => $this->business->uuid]))->assertSessionHas('success');

        $wallets = Wallet::query()->withoutGlobalScopes()->where('business_id', $this->business->id)->orderBy('id')->get();
        $this->assertCount(40, $wallets);
        $this->assertSame('+250788111222', $wallets->first()->kyc_contact_phone);
        Queue::assertPushed(RegisterMerchant::class, 40);
        $delays = collect(Queue::pushed(RegisterMerchant::class))->map(fn ($job) => now()->diffInSeconds($job->delay, true));
        $this->assertEqualsWithDelta(39 * 3, $delays->max(), 2);
        $this->assertDatabaseHas('activity_logs', ['action' => 'injongepay_wallets_imported', 'business_id' => $this->business->id]);

        $export = $this->get(route('injongepay.platform.wallets.export', ['business' => $this->business->uuid]));
        $export->assertOk();
        $this->assertStringContainsString($wallets->last()->merchant_code.',"Till 40"', $export->streamedContent());
    }

    public function test_one_bad_line_creates_nothing_and_says_which_line(): void
    {
        Queue::fake();
        $this->wallet(overrides: ['name' => 'Till 2']);
        $before = Wallet::query()->withoutGlobalScopes()->count();
        $this->asOperator();

        $this->withConfirmedPassword()->from(route('injongepay.platform.wallets.import'))
            ->post(route('injongepay.platform.wallets.import.store'), ['business' => $this->business->uuid, 'file' => $this->csv("name,tin,phone\nTill 1,100001498,\nTill 2,,\nTill 3,12,+1555\nTill 1,,\n")])
            ->assertRedirect(route('injongepay.platform.wallets.import'))
            ->assertSessionHas('import_errors', fn ($errors) => array_keys($errors) === [3, 4, 5]);

        $this->assertSame($before, Wallet::query()->withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_file_without_a_name_column_is_refused(): void
    {
        $this->asOperator();

        $this->withConfirmedPassword()->post(route('injongepay.platform.wallets.import.store'), ['business' => $this->business->uuid, 'file' => $this->csv("shop,tin\nA,1\n")])
            ->assertSessionHas('error', __('injongepay::injongepay.err_import_name_column'));
    }

    public function test_a_business_cannot_import_or_export_merchant_codes(): void
    {
        $this->asOwner();

        $this->get(route('injongepay.platform.wallets.import'))->assertForbidden();
        $this->get(route('injongepay.platform.wallets.export'))->assertForbidden();
        $this->withConfirmedPassword()->post(route('injongepay.platform.wallets.import.store'), ['business' => $this->business->uuid, 'file' => $this->csv("name\nA\n")])->assertForbidden();
    }

    public function test_one_participant_call_feeds_every_wallet_and_parks_unknown_codes(): void
    {
        $a = $this->wallet();
        $b = $this->wallet(overrides: ['name' => 'Second till']);
        $this->fakeBank(['/participant/transactions' => Http::response([
            $this->row('TX-A', $a->merchant_code),
            $this->row('TX-B', $b->merchant_code, '5000.00'),
            $this->row('TX-X', '55599999'),
        ])]);

        $summary = app(BankMirror::class)->pullAll(now()->setDate(2026, 11, 2), now()->setDate(2026, 11, 2));

        $this->assertSame(3, $summary['seen']);
        $this->assertSame(1, $summary['foreign']);
        $this->assertSame(1, BankTransaction::query()->where('wallet_id', $a->id)->count());
        $this->assertSame(1, BankTransaction::query()->where('wallet_id', $b->id)->count());
        $this->assertTrue(UnallocatedPayment::query()->where('bank_reference', 'TX-X')->exists());
        Http::assertSentCount(2); // token + one list, not one per wallet
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/transactions?merchantId'));
    }

    public function test_closing_a_day_for_every_wallet_reads_gtbank_once(): void
    {
        $a = $this->wallet();
        $b = $this->wallet(overrides: ['name' => 'Second till']);
        $this->fakeBank(['/participant/transactions' => Http::response([$this->row('TX-A', $a->merchant_code)])]);

        $result = app(Reconciliation::class)->closeDayForAll(now()->setDate(2026, 11, 2));

        $this->assertSame(['closed' => 2, 'unreachable' => false], $result);
        $this->assertSame(DailyClose::RECONCILED, DailyClose::query()->where('wallet_id', $b->id)->value('state'));
        $this->assertSame(1, DailyClose::query()->where('wallet_id', $a->id)->value('bank_count'));
        Http::assertSentCount(2);
    }

    public function test_gtbank_not_answering_leaves_every_day_unreachable_not_reconciled(): void
    {
        $a = $this->wallet();
        $this->fakeBank(['/participant/transactions' => Http::response('', 504)]);

        $this->assertTrue(app(Reconciliation::class)->closeDayForAll(now()->setDate(2026, 11, 2))['unreachable']);
        $this->assertSame(DailyClose::BANK_UNREACHABLE, DailyClose::query()->where('wallet_id', $a->id)->value('state'));
    }

    public function test_the_status_sync_takes_a_capped_batch_oldest_first(): void
    {
        foreach (range(1, SyncMerchantsCommand::PER_RUN + 5) as $i) {
            $this->wallet(overrides: ['name' => "Till {$i}", 'bank_status' => Wallet::BANK_PENDING, 'bank_synced_at' => now()->subMinutes(30 + $i)]);
        }
        $fresh = $this->wallet(overrides: ['name' => 'Just checked', 'bank_status' => Wallet::BANK_PENDING, 'bank_synced_at' => now()->subMinute()]);

        $due = SyncMerchantsCommand::due()->get();

        $this->assertCount(SyncMerchantsCommand::PER_RUN, $due);
        $this->assertNotContains($fresh->id, $due->pluck('id'));
        $this->assertSame('Till '.(SyncMerchantsCommand::PER_RUN + 5), $due->first()->name);
    }
}
