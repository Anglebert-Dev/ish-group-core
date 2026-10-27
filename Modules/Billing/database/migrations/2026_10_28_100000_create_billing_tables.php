<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->unsignedBigInteger('insert_by')->nullable();
        $table->unsignedBigInteger('update_by')->nullable();
        $table->unsignedBigInteger('delete_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }

    public function up(): void
    {
        // What is sold: modules + limits for a price per interval.
        Schema::create('billing_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('interval')->default('month'); // day | week | month | year
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('modules')->nullable();          // module names included
            $table->json('limits')->nullable();           // {"users": 5}; 0/absent = unlimited
            $table->json('features')->nullable();         // marketing bullet points
            $table->boolean('is_active')->default(true);
            $table->boolean('is_private')->default(false); // only a superadmin can assign it
            $table->json('business_ids')->nullable();     // visible only to these businesses
            $table->boolean('once_per_business')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $this->audit($table);
        });

        Schema::create('billing_coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('type');                        // percent | fixed
            $table->decimal('value', 14, 2);
            $table->string('duration')->default('once');   // once | repeating | forever
            $table->unsignedSmallInteger('duration_periods')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->json('package_ids')->nullable();       // null = every package
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        // Per-business billing profile: invoice details + credit balance.
        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('billing_packages')->nullOnDelete();
            // pending | trialing | active | past_due | suspended | canceled
            $table->string('status');
            // Terms frozen when subscribed: later package edits don't change them.
            $table->json('snapshot');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // Downgrade waiting for the period end.
            $table->foreignId('scheduled_package_id')->nullable()->constrained('billing_packages')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('billing_coupons')->nullOnDelete();
            $table->unsignedSmallInteger('coupon_periods_remaining')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $this->audit($table);

            $table->index(['business_id', 'status']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('number')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            // new | renewal | change — what paying it does to the subscription
            $table->string('reason');
            $table->foreignId('package_id')->nullable()->constrained('billing_packages')->nullOnDelete();
            $table->string('status')->default('open');    // open | paid | void
            $table->string('currency', 3);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);           // proration + account balance used
            $table->decimal('credit_from_balance', 14, 2)->default(0); // part of credit taken from the account balance
            $table->decimal('proration_excess', 14, 2)->default(0);    // proration beyond the total, credited on payment
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->string('tax_label')->nullable();
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('billed_to')->nullable();         // business details at issue time
            $table->text('notes')->nullable();
            $this->audit($table);

            $table->index(['business_id', 'status']);
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('type');                        // package | proration | discount | adjustment
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_amount', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method');                       // bank_transfer | mobile_money | cash | card | other
            $table->string('reference')->nullable();
            $table->date('paid_on');
            $table->string('proof_path')->nullable();
            $table->string('status')->default('pending');   // pending | approved | rejected
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $this->audit($table);

            $table->index(['status', 'created_at']);
        });

        Schema::create('billing_coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('billing_coupons')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_coupon_redemptions');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_accounts');
        Schema::dropIfExists('billing_coupons');
        Schema::dropIfExists('billing_packages');
    }
};
