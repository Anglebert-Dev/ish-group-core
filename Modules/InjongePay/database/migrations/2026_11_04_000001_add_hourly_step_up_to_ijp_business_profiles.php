<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ijp_business_profiles', function (Blueprint $table) {
            // Cents: above this total in one hour, the PIN alone is not enough.
            $table->bigInteger('step_up_hourly_above')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ijp_business_profiles', fn (Blueprint $table) => $table->dropColumn('step_up_hourly_above'));
    }
};
