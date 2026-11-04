<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\User;
use App\Services\Module\ModuleManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\CashoutService;
use Modules\InjongePay\Services\Legacy\LegacyImport;
use Modules\InjongePay\Services\Reconciliation;
use Modules\InjongePay\Support\PlatformSettings;
use Modules\InjongePayConnect\Services\Credentials;

/** The old app's database, shaped like production, imported into a fresh install. */
class LegacyImportTest extends InjongePayTestCase
{
    private const OLD = LegacyImport::CONNECTION;

    private const T = '2026-10-20 10:00:00';

    private const TABLES = [
        'institutions' => 'id uuid code name short_name tin type country city address contact_person contact_person_phone contact_person_email support_staff_id is_active is_internal fee_percentage created_at updated_at deleted_at',
        'users' => 'id uuid firstname lastname middlename email email_verified_at password language ban_date last_ip last_login is_approved institution_id two_factor_secret two_factor_confirmed_at otp remember_token created_at updated_at deleted_at',
        'roles' => 'id name',
        'model_has_roles' => 'role_id model_type model_id',
        'model_has_permissions' => 'permission_id model_type model_id',
        'wallets' => 'id uuid institution_id name merchant_code notification_phone available_balance is_active merchant_status gtbank_registration_error gtbank_registered_name gtbank_registered_owner gtbank_synced_at gtbank_business_owner gtbank_tin gtbank_contact_email insert_by update_by created_at updated_at deleted_at',
        'institution_settlement_destinations' => 'id uuid institution_id bank_name account_number account_name is_active insert_by update_by created_at updated_at deleted_at',
        'wallet_cashouts' => 'id uuid reference wallet_id institution_id amount settlement_bank_name settlement_account_number settlement_account_name status ledger_entry_id insert_by insert_by_name created_at updated_at gtbank_status failure_reason gtbank_reference cbs_transaction_reference',
        'buyer_payments' => 'id uuid wallet_id institution_id merchant_code reference bank_reference channel buyer_name buyer_phone gross_amount fee_rate fee_amount net_amount payment_status bank_status ledger_entry_id insert_by created_at updated_at cashout_id value_date initiating_party_code received_via bank_cut_amount platform_revenue_amount',
        'wallet_transfers' => 'id uuid reference from_wallet_id to_wallet_id from_institution_id to_institution_id amount description status from_ledger_entry_id to_ledger_entry_id insert_by created_at updated_at',
        'wallet_ledger_entries' => 'id uuid wallet_id institution_id reference description customer_name customer_phone credit debit balance_after status source source_type source_id insert_by insert_by_name created_at',
        'api_call_logs' => 'id uuid direction provider method path caller request_payload response_status response_payload error_message duration_ms created_at',
        'user_activities' => 'id uuid user_id institution_id name email model_name url_path action description user_agent ip_address params created_at updated_at',
        'fee_rate_changes' => 'id scope institution_id old_value new_value changed_by changed_by_name created_at',
        'payment_refunds' => 'id uuid reference payment_id wallet_id institution_id amount',
        'otp_authorizations' => 'id uuid user_id institution_id operation_type otp',
        'user_logins' => 'id uuid user_id otp',
        'notifications' => 'id data',
        'platform_settings' => 'key value',
        'failed_jobs' => 'id',
        'institution_api_credentials' => 'id uuid institution_id client_id client_secret_hash is_enabled last_used_at insert_by update_by created_at updated_at',
        'institution_api_tokens' => 'id institution_api_credential_id institution_id token_hash expires_at created_at',
        'partner_payment_confirmations' => 'id uuid payment_id wallet_id institution_id status confirmation_idempotency_key invoice_no ebm_receipt_no sdc_id confirmed_by confirmed_by_name confirmed_at unconfirmed_by unconfirmed_by_name unconfirmed_at unconfirm_reason created_at updated_at is_partial note invoice_total balance_after',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('InjongePayConnect');
        config(['database.connections.'.self::OLD => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge(self::OLD);

        foreach (self::TABLES as $table => $columns) {
            Schema::connection(self::OLD)->create($table, function ($t) use ($columns) {
                foreach (explode(' ', $columns) as $column) {
                    in_array($column, ['credit', 'debit'], true) ? $t->decimal($column, 15, 2)->nullable() : $t->text($column)->nullable();
                }
            });
        }

        // The test case's own business and wallets are not part of a fresh install.
        DB::table('ijp_wallets')->delete();
        DB::table('ijp_business_profiles')->delete();
        $this->seedOldApp();
    }

    private function old(string $table, array $row): void
    {
        $columns = explode(' ', self::TABLES[$table]);
        $defaults = array_intersect_key(['uuid' => (string) Str::uuid(), 'created_at' => self::T, 'updated_at' => self::T], array_flip($columns));

        DB::connection(self::OLD)->table($table)->insert($row + $defaults);
    }

    private function seedOldApp(): void
    {
        $this->old('institutions', ['id' => 45, 'name' => 'Platform', 'type' => 'other', 'country' => 'RW', 'is_active' => 1, 'is_internal' => 1]);
        $this->old('institutions', ['id' => 2, 'code' => 'INJP-1', 'name' => 'Old School', 'type' => 'school', 'tin' => '100001498', 'country' => 'RW', 'contact_person_email' => 'head@old.test', 'is_active' => 1, 'is_internal' => 0, 'fee_percentage' => '1.50']);
        $this->old('roles', ['id' => 1, 'name' => 'SuperAdmin']);
        $this->old('roles', ['id' => 2, 'name' => 'InstitutionAdmin']);
        $this->old('users', ['id' => 7, 'firstname' => 'Ada', 'lastname' => 'Head', 'email' => 'Head@Old.test', 'password' => Hash::make('old-password'), 'institution_id' => 2, 'is_approved' => 1, 'otp' => '123456', 'remember_token' => 'remember-me']);
        $this->old('model_has_roles', ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 7]);

        $this->old('wallets', ['id' => 1, 'institution_id' => 45, 'name' => 'Bank Cut Account', 'merchant_code' => '900001', 'available_balance' => '40.00', 'is_active' => 1, 'merchant_status' => 'ACTIVE']);
        $this->old('wallets', ['id' => 2, 'institution_id' => 45, 'name' => 'Platform Revenue Account', 'merchant_code' => '900002', 'available_balance' => '60.00', 'is_active' => 1, 'merchant_status' => 'ACTIVE']);
        $this->old('wallets', ['id' => 3, 'institution_id' => 2, 'name' => 'Main till', 'merchant_code' => '55500021', 'available_balance' => '9500.00', 'is_active' => 1, 'merchant_status' => 'ACTIVE']);
        $this->old('wallets', ['id' => 4, 'institution_id' => 2, 'name' => 'Off by staff', 'merchant_code' => '55500022', 'available_balance' => '0.00', 'is_active' => 0, 'merchant_status' => 'ACTIVE']);
        $this->old('wallets', ['id' => 5, 'institution_id' => 2, 'name' => 'Off by the bank', 'merchant_code' => '55500023', 'available_balance' => '0.00', 'is_active' => 0, 'merchant_status' => 'INACTIVE']);

        $this->old('institution_settlement_destinations', ['id' => 1, 'institution_id' => 2, 'bank_name' => 'BK', 'account_number' => '00040-1', 'account_name' => 'OLD SCHOOL', 'is_active' => 1]);

        $this->old('buyer_payments', ['id' => 10, 'wallet_id' => 3, 'institution_id' => 2, 'merchant_code' => '55500021', 'reference' => 'PAY-20261020-1', 'bank_reference' => 'TX-1', 'gross_amount' => '10000.00', 'fee_rate' => '1.00', 'fee_amount' => '100.00', 'net_amount' => '9900.00', 'bank_cut_amount' => '40.00', 'platform_revenue_amount' => '60.00', 'payment_status' => 'AVAILABLE', 'bank_status' => 'SUCCESS', 'received_via' => 'WEBHOOK', 'ledger_entry_id' => 1, 'value_date' => '2026-10-20']);
        $this->old('wallet_ledger_entries', ['id' => 1, 'wallet_id' => 3, 'institution_id' => 2, 'reference' => 'LED-1', 'credit' => '9900.00', 'debit' => '0.00', 'balance_after' => '9900.00', 'status' => 'COMPLETED', 'source' => 'payment', 'source_type' => 'App\\Models\\Payment\\Payment', 'source_id' => 10]);
        $this->old('wallet_ledger_entries', ['id' => 2, 'wallet_id' => 1, 'institution_id' => 45, 'reference' => 'LED-2', 'credit' => '40.00', 'debit' => '0.00', 'balance_after' => '40.00', 'status' => 'COMPLETED', 'source' => 'bank_cut']);
        $this->old('wallet_ledger_entries', ['id' => 3, 'wallet_id' => 2, 'institution_id' => 45, 'reference' => 'LED-3', 'credit' => '60.00', 'debit' => '0.00', 'balance_after' => '60.00', 'status' => 'COMPLETED', 'source' => 'platform_revenue']);

        // A cash-out GTBank hadn't settled when the old app stopped.
        $this->old('wallet_cashouts', ['id' => 20, 'reference' => 'CSH-20261020-1', 'wallet_id' => 3, 'institution_id' => 2, 'amount' => '400.00', 'settlement_bank_name' => 'BK', 'settlement_account_number' => '00040-1', 'settlement_account_name' => 'OLD SCHOOL', 'status' => 'PENDING', 'ledger_entry_id' => 4]);
        $this->old('wallet_ledger_entries', ['id' => 4, 'wallet_id' => 3, 'institution_id' => 2, 'reference' => 'CSH-20261020-1', 'credit' => '0.00', 'debit' => '400.00', 'balance_after' => '9500.00', 'status' => 'COMPLETED', 'source' => 'cashout', 'source_type' => 'App\\Models\\Cashout\\Cashout', 'source_id' => 20]);

        $this->old('institution_api_credentials', ['id' => 1, 'institution_id' => 2, 'client_id' => 'injp_old', 'client_secret_hash' => Hash::make('old-partner-secret'), 'is_enabled' => 1]);
        $this->old('institution_api_tokens', ['id' => 1, 'institution_api_credential_id' => 1, 'institution_id' => 2, 'token_hash' => hash('sha256', 'live-token'), 'expires_at' => now()->addMinutes(30)->toDateTimeString(), 'created_at' => self::T]);
        $this->old('institution_api_tokens', ['id' => 2, 'institution_api_credential_id' => 1, 'institution_id' => 2, 'token_hash' => hash('sha256', 'dead-token'), 'expires_at' => now()->subDay()->toDateTimeString(), 'created_at' => self::T]);
        $this->old('partner_payment_confirmations', ['id' => 1, 'payment_id' => 10, 'wallet_id' => 3, 'institution_id' => 2, 'status' => 'CONFIRMED', 'invoice_no' => 'INV-1', 'is_partial' => 0, 'confirmed_at' => self::T]);

        $this->old('api_call_logs', ['id' => 1, 'direction' => 'outbound', 'provider' => 'gtbank', 'method' => 'POST', 'path' => '/disbursement/lookup', 'request_payload' => '{"a":1}', 'response_status' => 504, 'response_payload' => '<html>gateway</html>', 'duration_ms' => 20000]);
        $this->old('user_activities', ['id' => 1, 'user_id' => 7, 'institution_id' => 2, 'name' => 'Ada Head', 'email' => 'head@old.test', 'action' => 'login', 'description' => 'Signed in']);
        $this->old('fee_rate_changes', ['id' => 1, 'scope' => 'platform', 'old_value' => '2.00', 'new_value' => '1.00', 'changed_by_name' => 'Ada', 'created_at' => self::T]);
        $this->old('otp_authorizations', ['id' => 1, 'user_id' => 7, 'institution_id' => 2, 'operation_type' => 'cashout', 'otp' => '654321']);
        $this->old('platform_settings', ['key' => 'payment_fee_percentage', 'value' => '1.00']);
        $this->old('platform_settings', ['key' => 'bank_cut_percentage', 'value' => '40']);
        $this->old('platform_settings', ['key' => 'gtbank_webhook_secret', 'value' => 'old-webhook-secret']);
    }

    private function imported(string $code = '55500021'): Wallet
    {
        return Wallet::query()->withoutGlobalScopes()->where('merchant_code', $code)->firstOrFail();
    }

    private function import(bool $commit = true): array
    {
        return app(LegacyImport::class)->run($commit);
    }

    public function test_a_dry_run_checks_everything_and_keeps_nothing(): void
    {
        $result = $this->import(false);

        $this->assertTrue($result['passed'], json_encode($result['checks']));
        $this->assertFalse($result['committed']);
        $this->assertSame(0, DB::table('ijp_payments')->count());
        $this->assertSame(0, DB::table('ijp_legacy_records')->count());
        $this->assertNull(User::query()->where('email', 'head@old.test')->first());
    }

    public function test_every_balance_and_total_arrives_to_the_cent_and_the_books_agree(): void
    {
        $result = $this->import();

        $this->assertTrue($result['committed'], json_encode($result['checks']));
        $this->assertSame(950000, $this->imported()->available_balance);
        $this->assertSame(0, $this->imported()->pending_balance);
        $this->assertSame(4000, Wallet::query()->withoutGlobalScopes()->where('internal_role', Wallet::ROLE_BANK_CUT)->value('available_balance'));
        $this->assertSame(6000, Wallet::query()->withoutGlobalScopes()->where('internal_role', Wallet::ROLE_PLATFORM_REVENUE)->value('available_balance'));

        $books = app(Reconciliation::class)->checkBooks();
        $this->assertSame([0, 0], [$books['drifts'], $books['platform_difference']]);

        $payment = DB::table('ijp_payments')->first();
        $this->assertSame(['TX-1', 1000000, 990000], [$payment->bank_reference, (int) $payment->gross_amount, (int) $payment->net_amount]);
        $this->assertNotNull($payment->cleared_at);
        $this->assertNotNull($payment->ledger_entry_id);
        $this->assertSame((string) DB::connection(self::OLD)->table('buyer_payments')->value('uuid'), (string) $payment->uuid);
    }

    public function test_the_business_people_and_settings_come_across(): void
    {
        $this->import();

        $user = User::query()->where('email', 'head@old.test')->firstOrFail();
        $this->assertTrue(Hash::check('old-password', $user->password));
        $this->assertTrue($user->business->canUseModule('InjongePay'));
        $this->assertSame($user->id, $user->business->owner_id);
        $this->assertSame('school', $user->business->type);
        $this->assertTrue($user->isBusinessAdmin());

        $this->assertSame('1.0000', PlatformSettings::feePercentage());
        $this->assertSame('40.0000', PlatformSettings::bankCutPercentage());
        $this->assertSame('old-webhook-secret', PlatformSettings::webhookSecret());
        $this->assertTrue(SettlementAccount::query()->withoutGlobalScopes()->firstOrFail()->isUsable());
    }

    public function test_a_switch_off_by_staff_stays_and_gtbanks_own_status_is_not_mistaken_for_one(): void
    {
        $this->import();

        $this->assertFalse($this->imported('55500022')->is_enabled);
        $this->assertTrue($this->imported('55500023')->is_enabled);
        $this->assertSame(Wallet::BANK_INACTIVE, $this->imported('55500023')->bank_status);
    }

    public function test_partners_keep_their_secret_and_live_token(): void
    {
        $this->import();
        $credentials = app(Credentials::class);

        $this->assertNotNull($credentials->resolve('live-token'));
        $this->assertNull($credentials->resolve('dead-token'));
        $this->assertNotNull($credentials->issue('injp_old', 'old-partner-secret'));
        $this->assertSame(1, DB::table('ijpc_confirmations')->where('invoice_no', 'INV-1')->count());
    }

    public function test_a_cashout_left_pending_is_asked_about_and_never_sent_again(): void
    {
        $this->import();
        $cashout = Cashout::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(Cashout::UNKNOWN, $cashout->status);

        $this->fakeBank(['/disbursement/status' => Http::response(['status' => 'ACSC', 'amount' => 400, 'cbs_transaction_reference' => 'M1'])]);
        app(CashoutService::class)->check($cashout);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/disbursement/status'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/disbursement/transfer'));
    }

    public function test_a_balance_that_does_not_match_its_entries_is_kept_and_shown(): void
    {
        DB::connection(self::OLD)->table('wallets')->where('id', 3)->update(['available_balance' => '9700.00']);

        $result = $this->import();

        $this->assertSame(970000, $this->imported()->available_balance);
        $this->assertSame(20000, (int) DB::table('ijp_ledger_entries')->where('source', 'migration_adjustment')->value('amount'));
        $this->assertTrue(DB::table('ijp_reconciliation_items')->where('kind', 'migration_difference')->exists());
        $this->assertStringContainsString('55500021', implode(' ', $result['attention']));
        $this->assertSame(0, app(Reconciliation::class)->checkBooks()['drifts']);
    }

    public function test_no_secret_reaches_the_archive(): void
    {
        $this->import();

        $archive = DB::table('ijp_legacy_records')->pluck('data')->implode(' ');

        $this->assertStringNotContainsString('654321', $archive);
        $this->assertStringNotContainsString('123456', $archive);
        $this->assertStringNotContainsString('remember-me', $archive);
        $this->assertStringNotContainsString('old-webhook-secret', $archive);
        $this->assertStringNotContainsString('$2y$', $archive);
        $this->assertSame(1, DB::table('ijp_legacy_records')->where('source_table', 'otp_authorizations')->count());
    }

    public function test_a_status_it_does_not_know_stops_it_before_anything_is_written(): void
    {
        DB::connection(self::OLD)->table('wallet_cashouts')->update(['status' => 'ON_HOLD']);

        try {
            $this->import();
            $this->fail('It should have refused.');
        } catch (InjongePayException $e) {
            $this->assertStringContainsString('ON_HOLD', $e->getMessage());
        }

        $this->assertSame(0, DB::table('ijp_wallets')->count());
    }

    public function test_a_broken_link_rolls_everything_back(): void
    {
        DB::connection(self::OLD)->table('buyer_payments')->update(['wallet_id' => 999]);

        try {
            $this->import();
            $this->fail('It should have stopped.');
        } catch (InjongePayException) {
        }

        $this->assertSame(0, DB::table('ijp_wallets')->count());
        $this->assertNull(User::query()->where('email', 'head@old.test')->first());
    }

    public function test_it_refuses_a_database_that_already_has_money_in_it(): void
    {
        $this->import();

        $this->expectException(InjongePayException::class);
        app(LegacyImport::class)->run(false);
    }

    public function test_imported_activity_opens_on_the_log_page(): void
    {
        $this->import();
        $this->asOperator();

        $this->get(route('activity-logs.index'))->assertOk();
    }
}
