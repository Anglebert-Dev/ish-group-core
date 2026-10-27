<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            // Plain text for now — Fleet Vehicles isn't a real module yet,
            // so this can't be a proper FK. Migrate to vehicle_id once it is.
            $table->string('vehicle_label');
            $table->string('maintenance_type');
            $table->text('description')->nullable();
            $table->date('date');
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('status')->default('scheduled');
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
        Schema::dropIfExists('vehicle_maintenances');
    }
};
