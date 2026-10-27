<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'is_active']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 20)->default('finished');
            $table->string('name');
            $table->string('sku', 60)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->text('description')->nullable();
            // Names of up to two variant options, e.g. "Size" and "Colour".
            $table->string('option1_name', 40)->nullable();
            $table->string('option2_name', 40)->nullable();
            $table->decimal('reorder_level', 15, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'type', 'is_active']);
        });

        // Stock is held per variant. An item without sizes/colours has one variant.
        Schema::create('inventory_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('option1', 60)->nullable();
            $table->string('option2', 60)->nullable();
            $table->string('sku', 60)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('reorder_level', 15, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'item_id']);
        });

        // The movement log: every quantity change is one row, never edited.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->uuid('batch')->index();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('inventory_variants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('inventory_stores')->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->nullableMorphs('reference');
            $table->string('reference_label', 100)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            $table->index(['variant_id', 'store_id']);
        });

        // Current on-hand per variant and store, kept in step with the movement log.
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('inventory_variants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('inventory_stores')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['variant_id', 'store_id']);
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_variants');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_stores');
    }
};
