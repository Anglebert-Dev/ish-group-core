<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ijp_cashouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            // Letters and digits only, at most 35 (GTBank's rule).
            $table->string('reference', 35)->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets');
            $table->foreignId('settlement_account_id')->nullable()->constrained('ijp_settlement_accounts')->nullOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('ijp_approvals')->nullOnDelete();
            $table->bigInteger('amount');
            // Where it goes, as it was when asked.
            $table->string('account', 20);
            $table->string('account_holder');
            $table->string('bank_name')->nullable();
            // QUEUED → SUBMITTING → SUBMITTED | UNKNOWN → COMPLETED | FAILED, or NEEDS_REVIEW
            $table->string('status', 20);
            // What GTBank said when we sent it: accepted | refused | unknown | duplicate
            $table->string('submit_outcome', 12)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('bank_status', 20)->nullable();
            $table->string('bank_reference', 64)->nullable();
            $table->string('cbs_reference', 64)->nullable();
            $table->string('receiver_names')->nullable();
            $table->string('webhook_status', 20)->nullable();
            $table->timestamp('webhook_at')->nullable();
            // The last status read, to require two agreeing signals.
            $table->string('last_answer', 20)->nullable();
            $table->unsignedSmallInteger('checks')->default(0);
            $table->unsignedSmallInteger('not_found_count')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->unsignedBigInteger('reversal_entry_id')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_check_at']);
            $table->index(['business_id', 'created_at']);
        });

        // Every change of a cash-out, who or what caused it, and what GTBank said.
        Schema::create('ijp_cashout_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashout_id')->constrained('ijp_cashouts')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('source', 12); // request | submit | poller | webhook | operator
            $table->text('note')->nullable();
            $table->json('details')->nullable();
            $table->unsignedBigInteger('bank_call_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_cashout_events');
        Schema::dropIfExists('ijp_cashouts');
    }
};
