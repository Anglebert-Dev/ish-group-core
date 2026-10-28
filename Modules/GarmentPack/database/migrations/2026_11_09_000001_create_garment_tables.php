<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garment_styles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            // The finished product (and its sizes/colours) this style makes.
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->text('description')->nullable();
            $table->decimal('labour_cost', 15, 2)->default(0);
            $table->decimal('overhead_cost', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'item_id']);
        });

        // Bill of materials: what one piece needs.
        Schema::create('garment_style_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('style_id')->constrained('garment_styles')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('inventory_variants');
            $table->decimal('quantity', 15, 4);
            $table->decimal('waste_percent', 6, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_style_materials');
        Schema::dropIfExists('garment_styles');
    }
};
