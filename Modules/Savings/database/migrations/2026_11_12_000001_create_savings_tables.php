<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is always a whole number of minor units (bigint), never a float.
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
        $owner = function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
        };

        Schema::create('sav_accounts', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->string('name');
            $table->string('type', 30);
            $table->string('currency', 3);
            $table->date('opening_date');
            $table->bigInteger('opening_balance')->default(0);
            $table->bigInteger('minimum_balance')->nullable();
            $table->string('institution')->nullable();
            $table->string('reference', 60)->nullable();
            $table->boolean('is_liquid')->default(true);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $audit($table);
        });

        Schema::create('sav_categories', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->foreignId('parent_id')->nullable()->constrained('sav_categories')->nullOnDelete();
            $table->string('name');
            $table->string('kind', 10);
            $table->string('color', 7)->nullable();
            $table->boolean('is_archived')->default(false);
            $audit($table);
        });

        Schema::create('sav_obligations', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->string('direction', 12); // receivable | payable | restore
            $table->string('counterparty')->nullable();
            $table->string('description');
            $table->string('currency', 3);
            $table->foreignId('account_id')->nullable()->constrained('sav_accounts')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $audit($table);
        });

        Schema::create('sav_transactions', function (Blueprint $table) use ($owner) {
            $owner($table);
            $table->string('type', 30);
            $table->date('effective_date');
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->string('reference', 100)->nullable();
            $table->bigInteger('amount');
            $table->string('currency', 3);
            // Foreign currency: what was actually paid/received, and the rate used.
            $table->bigInteger('original_amount')->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->string('exchange_rate', 32)->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 80);
            $table->string('source', 20)->default('manual');
            $table->foreignId('reverses_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->foreignId('refund_of_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('import_hash', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key']);
            $table->unique(['business_id', 'import_hash']);
            $table->index(['business_id', 'effective_date']);
        });

        // How each account changed (signed, in the account's currency).
        Schema::create('sav_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('sav_transactions')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->bigInteger('amount');
            $table->date('effective_date');

            $table->index(['account_id', 'effective_date']);
        });

        // What the money was for. Reversals carry negative amounts.
        Schema::create('sav_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('sav_transactions')->cascadeOnDelete();
            $table->string('nature', 20);
            $table->foreignId('category_id')->nullable()->constrained('sav_categories')->nullOnDelete();
            $table->foreignId('obligation_id')->nullable()->constrained('sav_obligations')->nullOnDelete();
            $table->bigInteger('amount');
            $table->date('effective_date');
            $table->string('note')->nullable();

            $table->index(['business_id', 'nature', 'effective_date']);
        });

        // Opening amounts, restoration basis, settlements of restore targets, adjustments, write-offs.
        Schema::create('sav_obligation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('obligation_id')->constrained('sav_obligations')->cascadeOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->foreignId('transaction_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->date('effective_date');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['obligation_id', 'type', 'transaction_id']);
        });

        Schema::create('sav_budgets', function (Blueprint $table) use ($owner) {
            $owner($table);
            $table->foreignId('category_id')->constrained('sav_categories')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['business_id', 'category_id', 'period_start']);
        });

        Schema::create('sav_goals', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->string('name');
            $table->bigInteger('target_amount');
            $table->string('currency', 3);
            $table->date('target_date')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('sav_accounts')->nullOnDelete();
            $table->bigInteger('monthly_contribution')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $audit($table);
        });

        Schema::create('sav_goal_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('sav_goals')->cascadeOnDelete();
            $table->string('kind', 20); // allocation (plan) | contribution (posted transfer)
            $table->bigInteger('amount');
            $table->foreignId('transaction_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->foreignId('related_goal_id')->nullable()->constrained('sav_goals')->nullOnDelete();
            $table->date('effective_date');
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['goal_id', 'kind', 'transaction_id']);
        });

        Schema::create('sav_recurring_rules', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->string('kind', 20); // income | expense | transfer | contribution
            $table->string('description');
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->foreignId('to_account_id')->nullable()->constrained('sav_accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('sav_categories')->nullOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained('sav_goals')->nullOnDelete();
            $table->bigInteger('amount');
            $table->boolean('is_variable')->default(false);
            $table->string('frequency', 10);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('confidence', 12)->default('confirmed');
            $table->boolean('is_active')->default(true);
            $audit($table);
        });

        // Planned / expected events: never touch balances until posted.
        Schema::create('sav_expected', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->string('kind', 20); // income | expense | transfer | contribution | repayment_in | repayment_out
            $table->string('description');
            $table->string('group_label')->nullable();
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->foreignId('to_account_id')->nullable()->constrained('sav_accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('sav_categories')->nullOnDelete();
            $table->foreignId('obligation_id')->nullable()->constrained('sav_obligations')->nullOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained('sav_goals')->nullOnDelete();
            $table->foreignId('recurring_rule_id')->nullable()->constrained('sav_recurring_rules')->nullOnDelete();
            $table->bigInteger('amount');
            $table->date('expected_date');
            $table->string('confidence', 12)->default('expected');
            $table->string('status', 12)->default('expected');
            $table->foreignId('transaction_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $audit($table);

            $table->unique(['recurring_rule_id', 'expected_date']);
            $table->index(['business_id', 'status', 'expected_date']);
        });

        Schema::create('sav_commitments', function (Blueprint $table) use ($owner, $audit) {
            $owner($table);
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->string('description');
            $table->bigInteger('amount');
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('open');
            $table->foreignId('transaction_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $audit($table);
        });

        Schema::create('sav_reconciliations', function (Blueprint $table) use ($owner) {
            $owner($table);
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->date('statement_date');
            $table->bigInteger('statement_balance');
            $table->bigInteger('ledger_balance');
            $table->bigInteger('difference');
            $table->string('status', 12);
            $table->text('explanation')->nullable();
            $table->foreignId('adjustment_transaction_id')->nullable()->constrained('sav_transactions')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sav_import_batches', function (Blueprint $table) use ($owner) {
            $owner($table);
            $table->foreignId('account_id')->constrained('sav_accounts');
            $table->string('filename');
            $table->string('status', 12)->default('preview');
            $table->json('rows');
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sav_alerts_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('key', 191);
            $table->timestamp('sent_at');

            $table->unique(['business_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach ([
            'sav_alerts_sent', 'sav_import_batches', 'sav_reconciliations', 'sav_commitments', 'sav_expected',
            'sav_recurring_rules', 'sav_goal_allocations', 'sav_goals', 'sav_budgets', 'sav_obligation_items',
            'sav_lines', 'sav_entries', 'sav_transactions', 'sav_obligations', 'sav_categories', 'sav_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
