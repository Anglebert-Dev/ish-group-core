<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('daily_price', 12, 2)->default(0)->after('vehicle_category_id');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE vehicles
                SET daily_price = (
                    SELECT daily_price FROM vehicle_categories
                    WHERE vehicle_categories.id = vehicles.vehicle_category_id
                )
                WHERE EXISTS (
                    SELECT 1 FROM vehicle_categories
                    WHERE vehicle_categories.id = vehicles.vehicle_category_id
                )
            ');
        } else {
            DB::statement('
                UPDATE vehicles
                INNER JOIN vehicle_categories ON vehicle_categories.id = vehicles.vehicle_category_id
                SET vehicles.daily_price = vehicle_categories.daily_price
            ');
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('daily_price');
        });
    }
};
