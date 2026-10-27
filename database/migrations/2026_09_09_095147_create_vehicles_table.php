<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();

            // Basic information
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('plate_number')->unique();
            $table->string('vin')->nullable()->unique();
            $table->string('color')->nullable();

            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();

            // Specifications
            $table->string('vehicle_type');
            $table->unsignedTinyInteger('seats');
            $table->string('transmission');
            $table->string('fuel_type');
            $table->string('engine_capacity')->nullable();
            $table->decimal('weight', 10, 2)->nullable();

            // Operational
            $table->unsignedInteger('mileage')->nullable();
            $table->string('required_license_category')->nullable();
            $table->string('status')->default('available');
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
