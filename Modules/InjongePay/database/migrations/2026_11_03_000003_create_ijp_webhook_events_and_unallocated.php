<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every webhook GTBank sends, stored before it is acted on, so none is lost.
        Schema::create('ijp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('kind', 20); // payment | disbursement
            // kind:identifier:status, so a redelivery is recognised.
            $table->string('event_key', 160)->unique();
            $table->json('payload');
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('deliveries')->default(1);
            $table->timestamp('processed_at')->nullable();
            // created | duplicate | parked | ignored | reversed | applied | failed
            $table->string('outcome', 20)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();
            $table->index(['processed_at', 'created_at']);
        });

        // Money GTBank credited that no wallet could take (unknown, switched-off or not yet active code).
        Schema::create('ijp_unallocated_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('bank_reference', 64)->unique();
            $table->string('merchant_code', 20)->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained('ijp_wallets')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->bigInteger('amount');
            $table->json('payload');
            $table->string('reason', 40); // unknown_code | wallet_disabled | not_active | internal
            $table->string('status', 20)->default('open'); // open | allocated | settled_outside
            $table->foreignId('payment_id')->nullable()->constrained('ijp_payments')->nullOnDelete();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_unallocated_payments');
        Schema::dropIfExists('ijp_webhook_events');
    }
};
