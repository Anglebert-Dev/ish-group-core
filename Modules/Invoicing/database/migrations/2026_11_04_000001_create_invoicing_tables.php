<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('store_id')->constrained('inventory_stores');
            // Optional sales order (SalesOrders module); no FK so Invoicing works alone.
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->string('status', 20)->default('unpaid');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            // Whether this invoice took the goods out of stock (not when a delivered order already did).
            $table->boolean('stock_issued')->default(false);
            $table->text('notes')->nullable();
            $table->string('credit_note_number', 40)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->boolean('stock_returned')->default(false);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'customer_id']);
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('inventory_variants');
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('number', 40);
            $table->date('paid_on');
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
