<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limits the platform sets for each business (cents; null = no limit).
        Schema::table('ijp_business_profiles', function (Blueprint $table) {
            $table->bigInteger('limit_per_transaction')->nullable();
            $table->bigInteger('limit_daily')->nullable();
            $table->bigInteger('limit_monthly')->nullable();
            // Above this, the PIN alone is not enough (authenticator or emailed code too).
            $table->bigInteger('step_up_above')->nullable();
            // Above this, a second person approves.
            $table->bigInteger('second_approval_above')->nullable();
        });

        // Where a business's cash-outs may go. Added and approved by the platform only.
        Schema::create('ijp_settlement_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('kind', 10); // bank | wallet
            $table->string('account', 20); // as GTBank wants it (07… for wallets)
            $table->string('holder_name');
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 10)->nullable();
            // What GTBank's lookup said, and when.
            $table->string('verified_names')->nullable();
            $table->string('verified_bank_code', 10)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verification_call_id')->nullable();
            // pending | verified | approved | disabled
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            // Cooling-off: no cash-out to a new or changed account before this.
            $table->timestamp('usable_from')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'account']);
        });

        Schema::create('ijp_transaction_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('pin_hash');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('set_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ijp_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Codes emailed for a step-up when the person has no authenticator app.
        Schema::create('ijp_step_up_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->string('purpose', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });

        // A money movement someone asked for, frozen as asked, until it runs.
        Schema::create('ijp_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->string('operation', 20); // cashout | transfer
            $table->json('payload');
            $table->bigInteger('amount');
            $table->foreignId('requested_by')->constrained('users');
            // awaiting_approval | approved | rejected | executed | failed | expired
            $table->string('status', 20);
            $table->json('checks')->nullable(); // what was asked of the requester and why
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });

        Schema::create('ijp_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('reference', 40)->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('from_wallet_id')->constrained('ijp_wallets');
            $table->foreignId('to_wallet_id')->constrained('ijp_wallets');
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->foreignId('approval_id')->nullable()->constrained('ijp_approvals')->nullOnDelete();
            $table->unsignedBigInteger('from_entry_id')->nullable();
            $table->unsignedBigInteger('to_entry_id')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ijp_transfers', 'ijp_approvals', 'ijp_step_up_codes', 'ijp_trusted_devices', 'ijp_transaction_pins', 'ijp_settlement_accounts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('ijp_business_profiles', function (Blueprint $table) {
            $table->dropColumn(['limit_per_transaction', 'limit_daily', 'limit_monthly', 'step_up_above', 'second_approval_above']);
        });
    }
};
