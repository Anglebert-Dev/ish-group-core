<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_logs', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }

            if (! Schema::hasColumn('fuel_logs', 'gas_station_name')) {
                $table->string('gas_station_name')->nullable()->after('vehicle_id');
            }

            if (! Schema::hasColumn('fuel_logs', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('gas_station_name')->constrained('drivers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_logs', 'driver_id')) {
                $table->dropConstrainedForeignId('driver_id');
            }

            if (Schema::hasColumn('fuel_logs', 'gas_station_name')) {
                $table->dropColumn('gas_station_name');
            }

            if (! Schema::hasColumn('fuel_logs', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('vehicle_id')->constrained('suppliers')->nullOnDelete();
            }
        });
    }
};
