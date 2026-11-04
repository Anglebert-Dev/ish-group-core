<?php

namespace Modules\InjongePay\Services\Legacy;

use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\Role\Role;
use App\Models\System\System;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Module\ModuleManager;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\ReconciliationItems;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformSettings;
use Modules\InjongePay\Support\Rate;
use Modules\InjongePayConnect\Profiles\PosEbmV1;
use Spatie\Permission\PermissionRegistrar;

/**
 * Moves the old Injonge Pay database into this app, once. The old database is
 * only read, inside one read-only snapshot. Everything here happens in one
 * transaction: when a total does not match, or for a dry run, it is rolled back
 * and this database stays as it was.
 */
class LegacyImport
{
    public const CONNECTION = 'injongepay_legacy';

    private const SECRET_COLUMNS = ['password', 'otp', 'otp_key', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'previous_passwords', 'email_verification_key', 'phone_number_verification_key', 'token'];

    private Connection $old;

    private int $operatorId;

    /** Old id => new id */
    private array $businesses = [];

    private array $users = [];

    private array $wallets = [];

    private array $settlements = [];

    private array $cashouts = [];

    private array $payments = [];

    private array $transfers = [];

    private array $entries = [];

    private array $connections = [];

    /** New wallet id => what the old app's balance differs from its entries by, in cents. */
    private array $differences = [];

    private array $counts = [];

    private array $attention = [];

    private array $checks = [];

    public function __construct(protected InternalAccounts $internal, protected ReconciliationItems $items)
    {
    }

    /** @return array{committed: bool, passed: bool, counts: array, checks: list<array>, attention: list<string>, platform_difference: int} */
    public function run(bool $commit): array
    {
        $this->connect();
        $this->refuseIfNotEmpty();

        $this->operatorId = (int) System::getProperty('primary_business_id');
        if (! Business::query()->whereKey($this->operatorId)->exists()) {
            throw new InjongePayException(__('injongepay::injongepay.err_no_operator'));
        }

        $this->refuseUnknownValues();

        DB::beginTransaction();

        try {
            ActivityLogService::muted(function () {
                $this->importBusinesses();
                $this->importUsers();
                $this->importWallets();
                $this->importSettlementAccounts();
                $this->importCashouts();
                $this->importPayments();
                $this->importTransfers();
                $this->importLedger();
                $this->linkLedger();
                $this->adjustDifferences();
                $this->importPartners();
                $this->importLogs();
                $this->importFeeHistory();
                $this->importArchive();
                $this->setCounters();
            });

            $this->verify();
            $passed = collect($this->checks)->every(fn ($c) => $c['ok']);
            $difference = app(\Modules\InjongePay\Services\Reconciliation::class)->platformDifference();
            $keep = $commit && $passed;

            if ($keep) {
                $this->importSettings();
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        } finally {
            if ($this->old->transactionLevel() > 0) {
                $this->old->rollBack();
            }
        }

        if ($keep) {
            $this->afterCommit();
        }

        return ['committed' => $keep, 'passed' => $passed, 'counts' => $this->counts, 'checks' => $this->checks, 'attention' => $this->attention, 'platform_difference' => $difference];
    }

    private function connect(): void
    {
        if (config('database.connections.'.self::CONNECTION) === null) {
            config(['database.connections.'.self::CONNECTION => config('injongepay.legacy')]);
        }

        if (blank(config('database.connections.'.self::CONNECTION.'.database'))) {
            throw new InjongePayException('Set LEGACY_DB_DATABASE, LEGACY_DB_USERNAME and LEGACY_DB_PASSWORD in .env first.');
        }

        $this->old = DB::connection(self::CONNECTION);

        if ($this->old->getDriverName() === 'pgsql') {
            // The database itself refuses any write on this connection.
            $this->old->statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
            $this->old->beginTransaction();
            $this->old->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        } else {
            $this->old->beginTransaction();
        }
    }

    private function refuseIfNotEmpty(): void
    {
        $used = DB::table('ijp_wallets')->whereNull('internal_role')->exists()
            || DB::table('ijp_payments')->exists() || DB::table('ijp_ledger_entries')->exists();

        if ($used) {
            throw new InjongePayException('This database already has Injonge Pay data, so the import was refused. It must run on a fresh install.');
        }
    }

    /** A status this code doesn't know stops everything before a single row is written. */
    private function refuseUnknownValues(): void
    {
        $unknown = fn (string $table, string $column, array $known) => $this->old->table($table)->whereNotIn($column, $known)->distinct()->pluck($column)->all();

        $found = array_filter([
            'buyer_payments.payment_status' => $unknown('buyer_payments', 'payment_status', ['AVAILABLE', 'CASHED_OUT', 'REFUNDED']),
            'wallet_cashouts.status' => $unknown('wallet_cashouts', 'status', ['COMPLETED', 'PENDING', 'FAILED']),
            'wallets.merchant_status' => $unknown('wallets', 'merchant_status', ['PENDING', 'ACTIVE', 'FAILED', 'CLOSED', 'INACTIVE']),
        ]);

        $both = $this->old->table('wallet_ledger_entries')->where('credit', '>', 0)->where('debit', '>', 0)->count();

        if ($found || $both) {
            throw new InjongePayException('The old database has values this import does not understand: '.json_encode($found + ['ledger entries with both a credit and a debit' => $both]));
        }
    }

    private function importBusinesses(): void
    {
        $types = (array) config('core.business_types');
        $modules = array_values(array_filter(['InjongePay', Schema::hasTable('ijpc_connections') ? 'InjongePayConnect' : null]));

        foreach ($this->rows('institutions') as $row) {
            if ($row->is_internal) {
                $this->businesses[$row->id] = $this->operatorId;
                $this->count('institutions (internal → Inhills)');

                continue;
            }

            if (! in_array($row->type, $types, true)) {
                $this->attention[] = "Business \"{$row->name}\": type \"{$row->type}\" does not exist here, set to \"other\". Change it on the business's page.";
            }

            $id = DB::table('businesses')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'name' => $row->name, 'type' => in_array($row->type, $types, true) ? $row->type : 'other',
                'is_active' => (bool) $row->is_active, 'email' => $row->contact_person_email ?: null, 'timezone' => config('app.timezone'),
                'enabled_modules' => json_encode($modules), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at,
            ]);
            $this->businesses[$row->id] = $id;

            Role::query()->create(['name' => Role::scopedName(Role::ADMIN, $id), 'guard_name' => 'staff', 'business_id' => $id, 'is_default' => true]);

            $tin = $row->tin ?: null;
            if ($tin && DB::table('ijp_business_profiles')->where('country', $row->country)->where('tin', $tin)->exists()) {
                $this->attention[] = "Business \"{$row->name}\": TIN {$tin} is already used by another business, left empty here (it stays in the archive).";
                $tin = null;
            }

            DB::table('ijp_business_profiles')->insert([
                'uuid' => (string) Str::uuid(), 'business_id' => $id, 'code' => $row->code, 'short_name' => $row->short_name, 'tin' => $tin,
                'country' => $row->country, 'city' => $row->city, 'address' => $row->address, 'contact_person' => $row->contact_person,
                'contact_phone' => $row->contact_person_phone, 'contact_email' => $row->contact_person_email, 'fee_percentage' => $row->fee_percentage,
                'is_internal' => false, 'legacy_id' => $row->id, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at,
            ]);

            $this->archive('institutions', $row->id, $id, $row);
            $this->count('businesses');
        }
    }

    private function importUsers(): void
    {
        $roles = $this->old->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->get(['model_has_roles.model_id', 'roles.name'])->groupBy('model_id')->map(fn ($r) => $r->pluck('name')->all());
        $direct = $this->old->table('model_has_permissions')->count();
        $morph = (new User)->getMorphClass();

        foreach ($this->rows('users') as $row) {
            $names = $roles->get($row->id, []);
            $businessId = $row->institution_id ? ($this->businesses[$row->institution_id] ?? null) : $this->operatorId;
            $email = Str::lower(trim((string) $row->email));
            $archived = (array) $row + ['roles' => $names];

            if ($businessId === null) {
                $this->attention[] = "User #{$row->id}: their institution does not exist; kept in the archive only.";
                $this->archive('users', $row->id, null, $archived);

                continue;
            }

            if ($email === '') {
                $email = "legacy-user-{$row->id}@no-email.invalid";
                $this->attention[] = "User #{$row->id} ({$row->firstname} {$row->lastname}) has no email, and email is how one signs in here. Created as {$email}: give them a real one.";
            }

            $existing = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->value('id');
            if ($existing) {
                $this->users[$row->id] = $existing;
                $this->archive('users', $row->id, $businessId, $archived);
                $this->attention[] = "User {$email} already exists here: that account was kept as it is, with its own password and role.";
                $this->count('users (already here)');

                continue;
            }

            $superadmin = in_array('SuperAdmin', $names, true);
            $id = DB::table('users')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'business_id' => $businessId, 'is_superadmin' => $superadmin,
                'name' => trim(implode(' ', array_filter([$row->firstname, $row->middlename, $row->lastname]))),
                'email' => $email, 'email_verified_at' => $row->email_verified_at, 'password' => $row->password,
                'mfa_secret' => $this->mfaSecret($row), 'mfa_enabled' => $this->mfaSecret($row) !== null,
                'status' => match (true) { $row->ban_date !== null => UserStatus::Banned->value, ! $row->is_approved => UserStatus::Pending->value, default => UserStatus::Active->value },
                'last_login_at' => $row->last_login ? date('Y-m-d H:i:s', (int) $row->last_login) : null, 'last_login_ip' => $row->last_ip,
                'locale' => $row->language, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at,
            ]);
            $this->users[$row->id] = $id;
            $this->archive('users', $row->id, $businessId, $archived);
            $this->count('users');

            if ($row->two_factor_confirmed_at && $this->mfaSecret($row) === null) {
                $this->attention[] = "{$email} used an authenticator. Set LEGACY_APP_KEY to carry it across, or they set it up again.";
            }

            if (in_array('InstitutionAdmin', $names, true)) {
                DB::table('model_has_roles')->insert(['role_id' => Role::query()->where('name', Role::scopedName(Role::ADMIN, $businessId))->value('id'), 'model_type' => $morph, 'model_id' => $id]);
                DB::table('businesses')->where('id', $businessId)->whereNull('owner_id')->update(['owner_id' => $id]);
            } elseif (! $superadmin) {
                $this->attention[] = "{$email} had the role(s) \"".implode(', ', $names).'" in the old app, which do not exist here. They have no role yet: give them one.';
            }
        }

        foreach ($this->rows('institutions') as $row) {
            if ($row->support_staff_id && isset($this->users[$row->support_staff_id])) {
                DB::table('ijp_business_profiles')->where('legacy_id', $row->id)->update(['support_staff_id' => $this->users[$row->support_staff_id]]);
            }
        }

        if ($direct > 0) {
            $this->attention[] = "{$direct} permissions were given directly to users in the old app. They don't carry over (the names differ): set them through roles.";
        }
    }

    private function mfaSecret(object $row): ?string
    {
        if (! $row->two_factor_confirmed_at || ! $row->two_factor_secret || ! ($key = config('injongepay.legacy_app_key'))) {
            return null;
        }

        try {
            $old = new Encrypter(base64_decode(Str::after($key, 'base64:')), 'AES-256-CBC');

            return Crypt::encryptString($old->decryptString($row->two_factor_secret));
        } catch (\Throwable) {
            return null;
        }
    }

    private function importWallets(): void
    {
        foreach ($this->rows('wallets') as $row) {
            $businessId = $this->business($row->institution_id);
            $role = $businessId === $this->operatorId ? $this->internalRole($row) : null;

            // The old sync copied GTBank's status into is_active, so a code GTBank
            // hasn't activated says nothing about whether staff switched it off.
            $values = [
                'uuid' => $this->uuid($row->uuid), 'name' => $row->name, 'merchant_code' => $row->merchant_code,
                'bank_status' => $row->merchant_status, 'bank_error' => $row->gtbank_registration_error, 'bank_registered_name' => $row->gtbank_registered_name,
                'bank_registered_owner' => $row->gtbank_registered_owner, 'bank_synced_at' => $row->gtbank_synced_at,
                'kyc_business_owner' => $row->gtbank_business_owner, 'kyc_tin' => $row->gtbank_tin, 'kyc_contact_phone' => $row->notification_phone, 'kyc_contact_email' => $row->gtbank_contact_email,
                'is_enabled' => (bool) $row->is_active || $row->merchant_status !== Wallet::BANK_ACTIVE,
                'pending_balance' => 0, 'available_balance' => Money::toCents($row->available_balance),
                'legacy_id' => $row->id, 'insert_by' => $this->user($row->insert_by), 'update_by' => $this->user($row->update_by),
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at,
            ];

            if ($role) {
                $id = $this->internal->wallet($role)->id;
                DB::table('ijp_wallets')->where('id', $id)->update($values);
            } else {
                $id = DB::table('ijp_wallets')->insertGetId($values + ['business_id' => $businessId]);
            }

            $this->wallets[$row->id] = $id;
            $this->archive('wallets', $row->id, $businessId, $row);
            $this->count('wallets');
        }

        DB::table('ijp_business_profiles')->where('business_id', $this->operatorId)->update(['is_internal' => true]);
    }

    private function internalRole(object $wallet): string
    {
        return match (true) {
            Str::contains(Str::lower($wallet->name), 'bank') => Wallet::ROLE_BANK_CUT,
            Str::contains(Str::lower($wallet->name), 'revenue') => Wallet::ROLE_PLATFORM_REVENUE,
            default => throw new InjongePayException("Internal wallet \"{$wallet->name}\" is neither the bank cut nor the platform revenue."),
        };
    }

    private function importSettlementAccounts(): void
    {
        $merged = 0;
        // Live accounts first: a business can hold one row per account here.
        $rows = collect($this->rows('institution_settlement_destinations'))->sortBy(fn ($r) => [$r->deleted_at === null ? 0 : 1, $r->is_active ? 0 : 1, -$r->id]);

        foreach ($rows as $row) {
            $businessId = $this->business($row->institution_id);
            $key = $businessId.':'.$row->account_number;

            if (isset($this->settlements[$key])) {
                $this->archive('institution_settlement_destinations', $row->id, $businessId, $row);
                $merged++;

                continue;
            }

            // Already in use in the old app (GTBank checked them there), so no new
            // cooling-off; the first cash-out to each still asks for the step-up.
            $this->settlements[$key] = DB::table('ijp_settlement_accounts')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'business_id' => $businessId,
                'kind' => preg_match('/^(\+?250|0)7\d{8}$/', (string) $row->account_number) ? 'wallet' : 'bank',
                'account' => $row->account_number, 'holder_name' => $row->account_name, 'bank_name' => $row->bank_name,
                'status' => $row->is_active ? SettlementAccount::APPROVED : SettlementAccount::DISABLED,
                'approved_at' => $row->created_at, 'usable_from' => $row->created_at, 'legacy_id' => $row->id,
                'insert_by' => $this->user($row->insert_by), 'update_by' => $this->user($row->update_by),
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at,
            ]);
            $this->count('settlement accounts');
        }

        if ($merged) {
            $this->attention[] = "{$merged} repeated settlement accounts (same business, same account) were merged; the extra rows are in the archive.";
        }

        $this->counts['settlement accounts merged'] = $merged;
    }

    private function importCashouts(): void
    {
        $open = 0;

        foreach ($this->rows('wallet_cashouts') as $row) {
            $businessId = $this->business($row->institution_id);
            $status = match ($row->status) { 'COMPLETED' => Cashout::COMPLETED, 'FAILED' => Cashout::FAILED, 'PENDING' => Cashout::UNKNOWN };

            $id = DB::table('ijp_cashouts')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'reference' => $row->reference, 'business_id' => $businessId, 'wallet_id' => $this->wallet($row->wallet_id),
                'settlement_account_id' => $this->settlements[$businessId.':'.$row->settlement_account_number] ?? null,
                'amount' => Money::toCents($row->amount), 'account' => (string) $row->settlement_account_number, 'account_holder' => (string) $row->settlement_account_name, 'bank_name' => $row->settlement_bank_name,
                'status' => $status, 'submit_outcome' => $status === Cashout::UNKNOWN ? 'unknown' : null, 'submitted_at' => $row->created_at,
                'bank_status' => $row->gtbank_status, 'bank_reference' => $row->gtbank_reference, 'cbs_reference' => $row->cbs_transaction_reference,
                'completed_at' => $status === Cashout::COMPLETED ? $row->updated_at : null, 'failed_at' => $status === Cashout::FAILED ? $row->updated_at : null,
                'failure_reason' => $row->failure_reason, 'legacy_id' => $row->id, 'insert_by' => $this->user($row->insert_by), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $this->cashouts[$row->id] = $id;
            $this->count('cash-outs');

            DB::table('ijp_cashout_events')->insert(['cashout_id' => $id, 'to_status' => $status, 'source' => 'migration', 'note' => "Imported from the old app ({$row->status}".($row->gtbank_status ? ", GTBank {$row->gtbank_status}" : '').')', 'actor_name' => $row->insert_by_name, 'created_at' => $row->updated_at]);

            if ($status === Cashout::UNKNOWN) {
                $open++;
            }
        }

        if ($open) {
            $this->attention[] = "{$open} cash-outs were still pending in the old app. They are never resent: the regular check asks GTBank for their status, and unclear ones go to staff under Cash-outs.";
        }
    }

    private function importPayments(): void
    {
        $noReference = 0;
        $refunded = 0;
        $notSuccess = 0;

        foreach ($this->rows('buyer_payments') as $row) {
            $noReference += $row->bank_reference ? 0 : 1;
            $refunded += $row->payment_status === 'REFUNDED' ? 1 : 0;
            $notSuccess += $row->bank_status === 'SUCCESS' ? 0 : 1;

            $this->payments[$row->id] = DB::table('ijp_payments')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'business_id' => $this->business($row->institution_id), 'wallet_id' => $this->wallet($row->wallet_id),
                'merchant_code' => $row->merchant_code, 'reference' => $row->reference, 'bank_reference' => $row->bank_reference ?: 'LEGACY-'.$row->reference,
                'channel' => $row->channel, 'buyer_name' => $row->buyer_name, 'buyer_phone' => $row->buyer_phone, 'value_date' => $row->value_date, 'initiating_party_code' => $row->initiating_party_code,
                'gross_amount' => Money::toCents($row->gross_amount), 'fee_rate' => Rate::normalise((string) $row->fee_rate), 'fee_amount' => Money::toCents($row->fee_amount),
                'bank_cut_amount' => Money::toCents($row->bank_cut_amount), 'platform_revenue_amount' => Money::toCents($row->platform_revenue_amount), 'net_amount' => Money::toCents($row->net_amount),
                'payment_status' => $row->payment_status === 'REFUNDED' ? Payment::STATUS_REVERSED : $row->payment_status,
                // Spendable in the old app, and its balance moves over as it is.
                'cleared_at' => $row->created_at,
                'received_via' => match ($row->received_via) { 'WEBHOOK' => Payment::VIA_WEBHOOK, 'MANUAL_SYNC' => Payment::VIA_BANK_PULL, default => Payment::VIA_MIGRATION },
                'cashout_id' => $row->cashout_id ? ($this->cashouts[$row->cashout_id] ?? null) : null,
                'legacy_id' => $row->id, 'insert_by' => $this->user($row->insert_by), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $this->count('payments');
        }

        if ($noReference) {
            $this->attention[] = "{$noReference} payments have no GTBank reference in the old app. They were given LEGACY-<reference>, so GTBank's list can never match them.";
        }
        if ($refunded) {
            $this->attention[] = "{$refunded} refunded payments are shown as reversed; each refund's own record is in the archive.";
        }
        if ($notSuccess) {
            $this->attention[] = "{$notSuccess} payments are not SUCCESS at GTBank in the old app, but they were credited. Check them with GTBank.";
        }
    }

    private function importTransfers(): void
    {
        $notCompleted = 0;
        $across = 0;

        foreach ($this->rows('wallet_transfers') as $row) {
            if ($row->status !== 'COMPLETED') {
                $this->archive('wallet_transfers', $row->id, $this->business($row->from_institution_id), $row);
                $notCompleted++;

                continue;
            }

            $across += $row->from_institution_id === $row->to_institution_id ? 0 : 1;
            $this->transfers[$row->id] = DB::table('ijp_transfers')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'reference' => $row->reference, 'business_id' => $this->business($row->from_institution_id),
                'from_wallet_id' => $this->wallet($row->from_wallet_id), 'to_wallet_id' => $this->wallet($row->to_wallet_id), 'amount' => Money::toCents($row->amount),
                'description' => $row->description, 'legacy_id' => $row->id, 'insert_by' => $this->user($row->insert_by), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $this->count('transfers');
        }

        $this->counts['transfers not completed (archive)'] = $notCompleted;

        if ($across) {
            $this->attention[] = "{$across} old transfers went between two businesses. They are kept as history under the sending business; this app no longer allows them.";
        }
    }

    private function importLedger(): void
    {
        $sources = ['Payment' => [$this->payments, (new Payment)->getMorphClass()], 'Cashout' => [$this->cashouts, (new Cashout)->getMorphClass()], 'Transfer' => [$this->transfers, (new Transfer)->getMorphClass()]];
        $references = [];
        $notCompleted = 0;

        foreach ($this->rows('wallet_ledger_entries') as $row) {
            $credit = Money::toCents($row->credit);
            $debit = Money::toCents($row->debit);
            $reference = isset($references[$row->reference]) ? $row->reference.'-'.$row->id : $row->reference;
            $references[$reference] = true;
            $notCompleted += $row->status === 'COMPLETED' ? 0 : 1;
            [$map, $morph] = $sources[class_basename((string) $row->source_type)] ?? [[], null];

            $this->entries[$row->id] = DB::table('ijp_ledger_entries')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'business_id' => $this->business($row->institution_id), 'wallet_id' => $this->wallet($row->wallet_id),
                'reference' => $reference, 'idempotency_key' => 'legacy:'.$row->id, 'direction' => $credit > 0 ? 'credit' : 'debit', 'bucket' => 'available', 'amount' => $credit ?: $debit,
                'pending_after' => 0, 'available_after' => Money::toCents($row->balance_after), 'source' => $row->source ?: 'legacy',
                'source_type' => isset($map[$row->source_id]) ? $morph : null, 'source_id' => $map[$row->source_id] ?? null,
                'description' => $row->description, 'counterparty_name' => $row->customer_name, 'counterparty_phone' => $row->customer_phone,
                'actor_id' => $this->user($row->insert_by), 'actor_name' => $row->insert_by_name, 'legacy_id' => $row->id, 'created_at' => $row->created_at,
            ]);
            $this->count('ledger entries');
        }

        if ($notCompleted) {
            $this->attention[] = "{$notCompleted} ledger entries were not COMPLETED in the old app, but they are counted like the old balance did.";
        }
    }

    private function linkLedger(): void
    {
        foreach ($this->rows('buyer_payments') as $row) {
            if (isset($this->entries[$row->ledger_entry_id])) {
                DB::table('ijp_payments')->where('id', $this->payments[$row->id])->update(['ledger_entry_id' => $this->entries[$row->ledger_entry_id]]);
            }
        }

        foreach ($this->rows('wallet_transfers') as $row) {
            if (isset($this->transfers[$row->id])) {
                DB::table('ijp_transfers')->where('id', $this->transfers[$row->id])->update(['from_entry_id' => $this->entries[$row->from_ledger_entry_id] ?? null, 'to_entry_id' => $this->entries[$row->to_ledger_entry_id] ?? null]);
            }
        }

        $returned = $this->old->table('wallet_ledger_entries')->where('source_type', 'like', '%Cashout')->where('credit', '>', 0)->pluck('id', 'source_id');

        foreach ($this->rows('wallet_cashouts') as $row) {
            DB::table('ijp_cashouts')->where('id', $this->cashouts[$row->id])->update([
                'ledger_entry_id' => $this->entries[$row->ledger_entry_id] ?? null,
                'reversal_entry_id' => isset($returned[$row->id]) ? ($this->entries[$returned[$row->id]] ?? null) : null,
            ]);
        }
    }

    /** The old balance is what each business sees, so it stands; the gap becomes a visible entry. */
    private function adjustDifferences(): void
    {
        foreach (DB::table('ijp_wallets')->whereNotNull('legacy_id')->get() as $wallet) {
            $sum = (int) DB::table('ijp_ledger_entries')->where('wallet_id', $wallet->id)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total")->value('total');
            $gap = (int) $wallet->available_balance - $sum;

            if ($gap === 0) {
                continue;
            }

            DB::table('ijp_ledger_entries')->insert([
                'uuid' => (string) Str::uuid(), 'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id, 'reference' => 'MIG-'.$wallet->merchant_code,
                'idempotency_key' => 'legacy:adjust:'.$wallet->id, 'direction' => $gap > 0 ? 'credit' : 'debit', 'bucket' => 'available', 'amount' => abs($gap),
                'pending_after' => 0, 'available_after' => $wallet->available_balance, 'source' => 'migration_adjustment',
                'description' => 'The old app showed this balance, but its entries added up to '.Money::toDecimal($sum), 'created_at' => now(),
            ]);
            $this->differences[$wallet->id] = $gap;
            $this->attention[] = "Wallet {$wallet->merchant_code} ({$wallet->name}): the old balance is ".Money::toDecimal((int) $wallet->available_balance).' but its ledger adds up to '.Money::toDecimal($sum).'. The balance was kept, an adjusting entry of '.Money::toDecimal($gap).' records the gap, and a reconciliation item stays open.';
        }
    }

    private function importPartners(): void
    {
        $credentials = $this->rows('institution_api_credentials');
        $confirmations = $this->rows('partner_payment_confirmations');

        if (! Schema::hasTable('ijpc_connections')) {
            if ($credentials || $confirmations) {
                throw new InjongePayException('The old app has partner data: install InjongePayConnect first.');
            }

            return;
        }

        $integrator = null;
        if ($credentials) {
            $integrator = DB::table('ijpc_integrators')->where('profile', PosEbmV1::NAME)->value('id')
                ?? DB::table('ijpc_integrators')->insertGetId(['uuid' => (string) Str::uuid(), 'name' => 'Injonge EBM/POS', 'profile' => PosEbmV1::NAME, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $connectionOf = [];
        $byCredential = [];
        foreach ($credentials as $row) {
            $businessId = $this->business($row->institution_id);
            $id = DB::table('ijpc_connections')->insertGetId([
                'uuid' => $this->uuid($row->uuid), 'integrator_id' => $integrator, 'business_id' => $businessId, 'client_id' => $row->client_id, 'client_secret_hash' => $row->client_secret_hash,
                'is_enabled' => (bool) $row->is_enabled, 'last_used_at' => $row->last_used_at, 'legacy_id' => $row->id, 'insert_by' => $this->user($row->insert_by), 'update_by' => $this->user($row->update_by),
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $byCredential[$row->id] = $id;
            $connectionOf[$row->institution_id] ??= $id;
            $this->count('partner connections');
        }

        // Live tokens keep working, so a POS isn't signed out at the switch.
        foreach ($this->rows('institution_api_tokens') as $row) {
            if ($row->expires_at > now()->toDateTimeString() && isset($byCredential[$row->institution_api_credential_id])) {
                DB::table('ijpc_tokens')->insert(['connection_id' => $byCredential[$row->institution_api_credential_id], 'business_id' => $this->business($row->institution_id), 'token_hash' => $row->token_hash, 'expires_at' => $row->expires_at, 'created_at' => $row->created_at ?? now()]);
                $this->count('partner tokens still valid');
            }
        }

        foreach ($confirmations as $row) {
            DB::table('ijpc_confirmations')->insert([
                'uuid' => $this->uuid($row->uuid), 'payment_id' => $this->payments[$row->payment_id] ?? throw new InjongePayException("Confirmation #{$row->id} points to a payment that does not exist."),
                'wallet_id' => $this->wallet($row->wallet_id), 'business_id' => $this->business($row->institution_id), 'connection_id' => $connectionOf[$row->institution_id] ?? null,
                'status' => $row->status, 'confirmation_idempotency_key' => $row->confirmation_idempotency_key, 'invoice_no' => $row->invoice_no, 'ebm_receipt_no' => $row->ebm_receipt_no, 'sdc_id' => $row->sdc_id,
                'is_partial' => (bool) $row->is_partial, 'note' => $row->note, 'invoice_total' => $row->invoice_total, 'balance_after' => $row->balance_after,
                'confirmed_by' => $row->confirmed_by, 'confirmed_by_name' => $row->confirmed_by_name, 'confirmed_at' => $row->confirmed_at,
                'unconfirmed_by' => $row->unconfirmed_by, 'unconfirmed_by_name' => $row->unconfirmed_by_name, 'unconfirmed_at' => $row->unconfirmed_at, 'unconfirm_reason' => $row->unconfirm_reason,
                'legacy_id' => $row->id, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $this->count('invoice confirmations');
        }
    }

    private function importLogs(): void
    {
        $partners = Schema::hasTable('ijpc_api_calls');

        foreach ($this->rows('api_call_logs') as $row) {
            if ($row->provider !== 'gtbank' && ! $partners) {
                $this->archive('api_call_logs', $row->id, null, $row);

                continue;
            }

            if ($row->provider === 'gtbank') {
                DB::table('ijp_bank_calls')->insert([
                    'uuid' => $this->uuid($row->uuid), 'direction' => $row->direction === 'inbound' ? 'in' : 'out', 'method' => $row->method, 'endpoint' => Str::limit($row->path, 250, ''),
                    'request' => $this->json($row->request_payload), 'http_status' => $row->response_status, 'response' => $this->json($row->response_payload),
                    'outcome' => match (true) { $row->response_status >= 200 && $row->response_status < 300 => 'ok', $row->response_status >= 400 && $row->response_status < 500 => 'rejected', default => 'unknown' },
                    'message' => $row->error_message, 'duration_ms' => (int) $row->duration_ms, 'caller' => $row->caller, 'legacy_id' => $row->id, 'created_at' => $row->created_at,
                ]);
            } else {
                DB::table('ijpc_api_calls')->insert([
                    'method' => $row->method, 'path' => Str::limit($row->path, 250, ''), 'status' => (int) $row->response_status, 'request' => $this->json($row->request_payload),
                    'response' => $this->json($row->response_payload), 'duration_ms' => (int) $row->duration_ms, 'caller' => $row->caller, 'legacy_id' => $row->id, 'created_at' => $row->created_at,
                ]);
            }
            $this->count('API call logs');
        }

        $batch = [];
        foreach ($this->rows('user_activities') as $row) {
            $batch[] = [
                'uuid' => $this->uuid($row->uuid), 'business_id' => $row->institution_id ? ($this->businesses[$row->institution_id] ?? null) : null, 'user_id' => $this->user($row->user_id),
                'actor_name' => $row->name ?: $row->email, 'action' => Str::limit($row->action, 250, ''), 'subject_label' => $row->model_name, 'description' => $row->description,
                'properties' => json_encode(['legacy' => true, 'url' => $row->url_path, 'params' => json_decode((string) $row->params, true)], JSON_INVALID_UTF8_SUBSTITUTE), 'ip_address' => $row->ip_address, 'user_agent' => $row->user_agent,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ];
            $this->count('activity log rows');

            if (count($batch) === 500) {
                DB::table('activity_logs')->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('activity_logs')->insert($batch);
        }
    }

    private function importFeeHistory(): void
    {
        foreach ($this->rows('fee_rate_changes') as $row) {
            DB::table('ijp_fee_rate_changes')->insert([
                'scope' => $row->scope, 'business_id' => $row->institution_id ? ($this->businesses[$row->institution_id] ?? null) : null, 'old_value' => $row->old_value, 'new_value' => $row->new_value,
                'changed_by' => $this->user($row->changed_by), 'changed_by_name' => $row->changed_by_name, 'created_at' => $row->created_at,
            ]);
            $this->count('fee rate changes');
        }
    }

    private function importArchive(): void
    {
        foreach (['payment_refunds' => 'id', 'otp_authorizations' => 'id', 'user_logins' => 'id', 'notifications' => 'id', 'platform_settings' => 'key'] as $table => $key) {
            foreach ($this->rows($table, $key) as $row) {
                $business = isset($row->institution_id) ? ($this->businesses[$row->institution_id] ?? null) : null;
                $data = (array) $row;

                if ($table === 'platform_settings') {
                    unset($data['value']);
                }

                $this->archive($table, $row->{$key}, $business, $data);
            }
        }

        if ($count = $this->old->table('failed_jobs')->count()) {
            $this->attention[] = "{$count} failed background jobs are sitting in the old app. Look at them before switching it off: one could be a payment that was never processed.";
        }

        if ($this->old->table('platform_settings')->where('key', 'gtbank_webhook_secret_check_enabled')->value('value') === '0') {
            $this->attention[] = 'The webhook secret check was off in the old app. GTBank sends no secret, so set its addresses under Platform settings → Webhook allowed IPs before the switch, or its webhooks are refused.';
        }
    }

    private function setCounters(): void
    {
        $prefix = (string) config('injongepay.merchant_code_prefix', '555');
        $codes = DB::table('ijp_wallets')->pluck('merchant_code');
        $highest = (int) $codes->filter(fn ($c) => str_starts_with($c, $prefix))->map(fn ($c) => (int) substr($c, strlen($prefix)))->max();
        $internal = (int) $codes->filter(fn ($c) => ! str_starts_with($c, $prefix))->map(fn ($c) => (int) $c)->max();

        if ($this->old->getDriverName() === 'pgsql') {
            // Numbers the sequence already gave out are never handed out again.
            $highest = max($highest, (int) $this->old->selectOne('select last_value from wallet_merchant_code_seq')->last_value);
        }

        foreach (['merchant_code' => $highest, 'merchant_code:internal' => $internal] as $key => $value) {
            DB::table('ijp_counters')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }

        $this->counts['next merchant code'] = $prefix.str_pad((string) ($highest + 1), (int) config('injongepay.merchant_code_digits', 5), '0', STR_PAD_LEFT);
    }

    private function importSettings(): void
    {
        $settings = collect($this->rows('platform_settings', 'key'))->pluck('value', 'key');

        foreach (['payment_fee_percentage' => 'fee_percentage', 'bank_cut_percentage' => 'bank_cut_percentage'] as $old => $new) {
            if (filled($settings->get($old))) {
                PlatformSettings::set($new, (string) $settings->get($old));
            }
        }

        if (filled($settings->get('gtbank_webhook_secret'))) {
            PlatformSettings::setWebhookSecret((string) $settings->get('gtbank_webhook_secret'));
        }
    }

    /** Every total compared with the old database. One mismatch and nothing is kept. */
    private function verify(): void
    {
        $count = fn (string $table) => $this->old->table($table)->count();
        $legacy = fn (string $table) => DB::table($table)->whereNotNull('legacy_id')->count();
        $sum = fn (string $table, string $column) => Money::toCents((string) ($this->old->table($table)->sum($column) ?: '0'));
        $total = fn (string $table, string $column) => (int) DB::table($table)->whereNotNull('legacy_id')->sum($column);
        $archived = fn (string $table) => DB::table('ijp_legacy_records')->where('source_table', $table)->count();

        $this->check('Wallets', $count('wallets'), $legacy('ijp_wallets'));
        $this->check('Payments', $count('buyer_payments'), $legacy('ijp_payments'));
        $this->check('Cash-outs', $count('wallet_cashouts'), $legacy('ijp_cashouts'));
        $this->check('Transfers', $count('wallet_transfers'), $legacy('ijp_transfers') + $archived('wallet_transfers'));
        $this->check('Ledger entries', $count('wallet_ledger_entries'), $legacy('ijp_ledger_entries'));
        $this->check('Settlement accounts', $count('institution_settlement_destinations'), $legacy('ijp_settlement_accounts') + $archived('institution_settlement_destinations'));
        $this->check('Users', $count('users'), $archived('users'));
        $this->check('Refund records', $count('payment_refunds'), $archived('payment_refunds'));
        $this->check('Authorization history', $count('otp_authorizations'), $archived('otp_authorizations'));
        $this->check('Login history', $count('user_logins'), $archived('user_logins'));
        $this->check('Notifications', $count('notifications'), $archived('notifications'));

        if (Schema::hasTable('ijpc_connections')) {
            $this->check('Partner connections', $count('institution_api_credentials'), $legacy('ijpc_connections'));
            $this->check('Invoice confirmations', $count('partner_payment_confirmations'), $legacy('ijpc_confirmations'));
        }

        $this->check('Σ payments gross', $sum('buyer_payments', 'gross_amount'), $total('ijp_payments', 'gross_amount'), true);
        $this->check('Σ payments fee', $sum('buyer_payments', 'fee_amount'), $total('ijp_payments', 'fee_amount'), true);
        $this->check('Σ payments net', $sum('buyer_payments', 'net_amount'), $total('ijp_payments', 'net_amount'), true);
        $this->check('Σ bank cut', $sum('buyer_payments', 'bank_cut_amount'), $total('ijp_payments', 'bank_cut_amount'), true);
        $this->check('Σ platform revenue', $sum('buyer_payments', 'platform_revenue_amount'), $total('ijp_payments', 'platform_revenue_amount'), true);
        $this->check('Σ cash-outs', $sum('wallet_cashouts', 'amount'), $total('ijp_cashouts', 'amount'), true);
        $this->check('Σ ledger credits', $sum('wallet_ledger_entries', 'credit'), (int) DB::table('ijp_ledger_entries')->whereNotNull('legacy_id')->where('direction', 'credit')->sum('amount'), true);
        $this->check('Σ ledger debits', $sum('wallet_ledger_entries', 'debit'), (int) DB::table('ijp_ledger_entries')->whereNotNull('legacy_id')->where('direction', 'debit')->sum('amount'), true);
        $this->check('Σ wallet balances', $sum('wallets', 'available_balance'), $total('ijp_wallets', 'available_balance'), true);

        $different = 0;
        foreach ($this->old->table('wallets')->get(['id', 'available_balance']) as $wallet) {
            $different += Money::toCents($wallet->available_balance) === (int) DB::table('ijp_wallets')->where('id', $this->wallets[$wallet->id])->value('available_balance') ? 0 : 1;
        }
        $this->check('Wallets with a different balance', 0, $different);

        $broken = 0;
        foreach (DB::table('ijp_wallets')->whereNotNull('legacy_id')->get() as $wallet) {
            $sumNow = (int) DB::table('ijp_ledger_entries')->where('wallet_id', $wallet->id)->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total")->value('total');
            $broken += $sumNow === (int) $wallet->available_balance ? 0 : 1;
        }
        $this->check('Wallets whose entries do not add up here', 0, $broken);
    }

    private function check(string $label, int $old, int $new, bool $money = false): void
    {
        $this->checks[] = ['label' => $label, 'old' => $money ? Money::toDecimal($old) : (string) $old, 'new' => $money ? Money::toDecimal($new) : (string) $new, 'ok' => $old === $new];
    }

    private function afterCommit(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->differences as $walletId => $gap) {
            $wallet = Wallet::query()->withoutGlobalScopes()->find($walletId);
            $this->items->open('migration_difference', (string) $walletId, $wallet, $wallet, $gap);
        }

        foreach (Business::query()->whereIn('id', array_values(array_unique($this->businesses)))->whereKeyNot($this->operatorId)->whereNotNull('owner_id')->get() as $business) {
            app(ModuleManager::class)->hook('after_business_created', ['business' => $business, 'owner' => User::query()->find($business->owner_id), 'context' => []]);
        }

        audit('injongepay_legacy_imported', __('injongepay::injongepay.audit_legacy_imported'), null, ['counts' => $this->counts, 'attention' => count($this->attention)]);
    }

    /** Whole table, oldest first, read a chunk at a time. */
    private function rows(string $table, string $key = 'id'): array
    {
        $rows = [];
        $this->old->table($table)->orderBy($key)->chunk(2000, function ($chunk) use (&$rows) {
            array_push($rows, ...$chunk->all());
        });

        return $rows;
    }

    private function business(int|string|null $id): int
    {
        return $this->businesses[$id] ?? throw new InjongePayException("A row points to institution #{$id}, which does not exist.");
    }

    private function wallet(int|string|null $id): int
    {
        return $this->wallets[$id] ?? throw new InjongePayException("A row points to wallet #{$id}, which does not exist.");
    }

    private function user(int|string|null $id): ?int
    {
        return $id ? ($this->users[$id] ?? null) : null;
    }

    private function uuid(?string $value): string
    {
        return Str::isUuid((string) $value) ? Str::lower($value) : (string) Str::uuid();
    }

    private function json(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return json_validate($text) ? $text : json_encode(['raw' => $text], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function count(string $label): void
    {
        $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;
    }

    private function archive(string $table, int|string $id, ?int $businessId, object|array $row): void
    {
        DB::table('ijp_legacy_records')->insert([
            'source_table' => $table, 'legacy_id' => (string) $id, 'business_id' => $businessId,
            'data' => json_encode(array_diff_key((array) $row, array_flip(self::SECRET_COLUMNS)), JSON_INVALID_UTF8_SUBSTITUTE), 'created_at' => now(),
        ]);
    }
}
