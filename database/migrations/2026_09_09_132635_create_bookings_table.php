<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('booking_number')->unique();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporate_customer_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('pickup_station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('return_station_id')->constrained('stations')->restrictOnDelete();
            $table->dateTime('pickup_at');
            $table->dateTime('return_at');
            $table->unsignedInteger('rental_days');

            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->decimal('category_daily_price', 12, 2);

            $table->string('rental_type');
            $table->string('driver_license_number')->nullable();
            $table->string('driver_license_category')->nullable();

            $table->string('payment_option');
            $table->decimal('total_amount', 12, 2);

            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->string('status')->default('pending');
            $table->text('cancellation_reason')->nullable();
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
        Schema::dropIfExists('bookings');
    }
};
