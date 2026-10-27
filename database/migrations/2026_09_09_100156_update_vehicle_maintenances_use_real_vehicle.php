<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('uuid')->constrained('vehicles')->nullOnDelete();
            $table->dropColumn('vehicle_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->string('vehicle_label')->nullable();
        });
    }
};
