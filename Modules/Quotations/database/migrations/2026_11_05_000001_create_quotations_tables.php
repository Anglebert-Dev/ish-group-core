<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('store_id')->constrained('inventory_stores');
            $table->string('status', 20)->default('open');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->string('sample_status', 20)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('inventory_variants');
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Advance payments: a list, each reversible, applied to the invoice later.
        Schema::create('quotation_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();
            $table->unsignedBigInteger('applied_invoice_id')->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_deposits');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
