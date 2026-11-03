<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is a whole number of cents (bigint), never a float. Every table keeps
 * the production uuid and a legacy_id so the import can be checked row by row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table) {
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('ijp_business_profiles', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 40)->nullable()->unique();
            $table->string('short_name')->nullable();
            $table->string('tin', 20)->nullable();
            $table->string('country', 2)->default('RW');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            // Null = the platform's rate.
            $table->decimal('fee_percentage', 5, 2)->nullable();
            $table->foreignId('support_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $audit($table);
            $table->unique(['country', 'tin']);
        });

        Schema::create('ijp_wallets', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('merchant_code', 12)->unique();
            // bank_cut | platform_revenue for the platform's own wallets.
            $table->string('internal_role', 20)->nullable()->unique();
            // What GTBank says: NOT_REGISTERED, REGISTERING, PENDING, ACTIVE, INACTIVE, CLOSED, FAILED.
            $table->string('bank_status', 20)->default('NOT_REGISTERED');
            $table->text('bank_error')->nullable();
            $table->timestamp('bank_error_at')->nullable();
            $table->string('bank_registered_name')->nullable();
            $table->string('bank_registered_owner')->nullable();
            $table->timestamp('bank_synced_at')->nullable();
            // A /merchants/update GTBank accepted but hasn't applied yet.
            $table->timestamp('bank_update_pending_at')->nullable();
            // Wallet-level KYC sent to GTBank; blank = the business profile's.
            $table->string('kyc_business_owner')->nullable();
            $table->string('kyc_tin', 20)->nullable();
            $table->string('kyc_contact_phone', 20)->nullable();
            $table->string('kyc_contact_email')->nullable();
            // The platform's own switch, separate from what GTBank says.
            $table->boolean('is_enabled')->default(true);
            $table->bigInteger('pending_balance')->default(0);
            $table->bigInteger('available_balance')->default(0);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $audit($table);
            $table->index(['business_id', 'bank_status']);
        });

        // Locked counters for references and merchant codes (portable: no database sequences).
        Schema::create('ijp_counters', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('ijp_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->string('merchant_code', 12);
            $table->string('reference', 40)->unique();
            // GTBank transactionId.
            $table->string('bank_reference', 64)->unique();
            $table->string('channel')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone', 30)->nullable();
            $table->date('value_date')->nullable();
            $table->string('initiating_party_code', 30)->nullable();
            $table->bigInteger('gross_amount');
            $table->decimal('fee_rate', 5, 2);
            $table->bigInteger('fee_amount');
            $table->bigInteger('bank_cut_amount')->default(0);
            $table->bigInteger('platform_revenue_amount')->default(0);
            $table->bigInteger('net_amount');
            // AVAILABLE, CASHED_OUT, REVERSED (bank took it back).
            $table->string('payment_status', 20)->default('AVAILABLE');
            // Set once the bank's own transaction list confirms it; only then can it be cashed out.
            $table->timestamp('cleared_at')->nullable();
            // WEBHOOK, BANK_PULL, MIGRATION.
            $table->string('received_via', 20)->default('WEBHOOK');
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->unsignedBigInteger('cashout_id')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'payment_status']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        // Append-only. A wallet's balances are the *_after of its last entry.
        Schema::create('ijp_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->string('direction', 6); // credit | debit
            $table->string('bucket', 10); // pending | available
            $table->bigInteger('amount');
            $table->bigInteger('pending_after');
            $table->bigInteger('available_after');
            $table->string('source', 30);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_phone', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'id']);
            $table->index(['source_type', 'source_id']);
        });

        // The fee share of each payment, credited to the platform wallets in batches
        // so payments never wait on the same two rows.
        Schema::create('ijp_fee_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('ijp_payments')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->string('kind', 20); // bank_cut | platform_revenue
            $table->bigInteger('amount');
            // Cancelled when the bank reverses the payment before it was swept.
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('swept_at')->nullable();
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payment_id', 'kind']);
            $table->index(['wallet_id', 'swept_at']);
        });

        Schema::create('ijp_fee_rate_changes', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20); // global_fee | global_bank_cut | business_fee
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->decimal('old_value', 5, 2)->nullable();
            $table->decimal('new_value', 5, 2)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['ijp_fee_rate_changes', 'ijp_fee_accruals', 'ijp_ledger_entries', 'ijp_payments', 'ijp_counters', 'ijp_wallets', 'ijp_business_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
