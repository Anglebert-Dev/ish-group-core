<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('mileage');
            $table->string('fuel_level');
            $table->text('condition_notes')->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->decimal('damage_charges', 12, 2)->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
