<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('national_id')->nullable()->unique()->after('phone');
            $table->text('address')->nullable()->after('national_id');
            $table->string('driver_license_number')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropColumn(['national_id', 'address', 'driver_license_number']);
        });
    }
};
