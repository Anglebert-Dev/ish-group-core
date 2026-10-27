<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('variant_id')->constrained('inventory_variants');
            $table->foreignId('store_id')->constrained('inventory_stores');
            // Optional link to a sales order (SalesOrders module); no FK so Production works alone.
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->decimal('quantity', 15, 3);
            $table->string('status', 20)->default('planned');
            // Stage names copied when created, and the cumulative count reached at each.
            $table->json('stages');
            $table->json('counts');
            $table->string('line', 60)->nullable();
            $table->decimal('daily_target', 15, 3)->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status']);
        });

        // Every count entered, never edited: the history behind reports.
        Schema::create('production_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('production_work_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->string('stage_name', 60);
            $table->decimal('quantity', 15, 3);
            $table->decimal('count_after', 15, 3);
            $table->date('work_date');
            $table->string('line', 60)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_entries');
        Schema::dropIfExists('production_work_orders');
    }
};
