<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GTBank's own list of transactions, as it reports them (the bank is the truth).
        Schema::create('ijp_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('transaction_id', 64)->unique();
            $table->string('merchant_code', 20)->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained('ijp_wallets')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->bigInteger('amount');
            $table->string('status', 20);
            $table->date('value_date')->nullable();
            $table->timestamp('bank_created_at')->nullable();
            $table->json('payload');
            $table->string('source', 10); // webhook | pull
            $table->foreignId('payment_id')->nullable()->constrained('ijp_payments')->nullOnDelete();
            // matched | ingested | mismatch | held | ignored
            $table->string('match_state', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'value_date']);
        });

        // Something that doesn't agree with GTBank, until a person explains it.
        Schema::create('ijp_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('kind', 30);
            // One open item per kind and subject.
            $table->string('item_key', 160)->unique();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('ijp_wallets')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 12)->default('open'); // open | resolved
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        // Per merchant code and day: GTBank's count and total against ours.
        Schema::create('ijp_daily_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('bank_count')->default(0);
            $table->bigInteger('bank_total')->default(0);
            $table->unsignedInteger('our_count')->default(0);
            $table->bigInteger('our_total')->default(0);
            // reconciled | differences | bank_unreachable
            $table->string('state', 20);
            $table->json('differences')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_daily_closes');
        Schema::dropIfExists('ijp_reconciliation_items');
        Schema::dropIfExists('ijp_bank_transactions');
    }
};
