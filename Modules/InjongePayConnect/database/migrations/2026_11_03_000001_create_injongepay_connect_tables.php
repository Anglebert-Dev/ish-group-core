<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A partner company (e.g. the Injonge EBM/POS) and the contract it speaks.
        Schema::create('ijpc_integrators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->string('profile', 40);
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // One integrator working for one business: its credentials.
        Schema::create('ijpc_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('integrator_id')->constrained('ijpc_integrators');
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('rate_limit')->nullable();
            $table->string('allowed_ips', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ijpc_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('ijpc_connections')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('expires_at');
        });

        // A payment linked to a partner's invoice (one active per payment).
        Schema::create('ijpc_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('payment_id')->constrained('ijp_payments')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('ijp_wallets')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('ijpc_connections')->nullOnDelete();
            $table->string('status', 12); // CONFIRMED | UNCONFIRMED
            $table->string('confirmation_idempotency_key')->nullable();
            $table->string('invoice_no');
            $table->string('ebm_receipt_no')->nullable();
            $table->string('sdc_id')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->string('note')->nullable();
            $table->decimal('invoice_total', 18, 2)->nullable();
            $table->decimal('balance_after', 18, 2)->nullable();
            $table->string('confirmed_by')->nullable();
            $table->string('confirmed_by_name')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('unconfirmed_by')->nullable();
            $table->string('unconfirmed_by_name')->nullable();
            $table->timestamp('unconfirmed_at')->nullable();
            $table->text('unconfirm_reason')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['payment_id', 'status']);
            $table->index(['business_id', 'invoice_no']);
            $table->index(['wallet_id', 'status']);
            $table->index('updated_at');
        });

        Schema::create('ijpc_api_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->nullable()->constrained('ijpc_connections')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('method', 8);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status');
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('caller')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['ijpc_api_calls', 'ijpc_confirmations', 'ijpc_tokens', 'ijpc_connections', 'ijpc_integrators'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
