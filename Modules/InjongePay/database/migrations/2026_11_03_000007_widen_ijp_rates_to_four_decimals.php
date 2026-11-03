<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rates such as 0.025% need more than 2 decimals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ijp_business_profiles', fn (Blueprint $table) => $table->decimal('fee_percentage', 7, 4)->nullable()->change());
        Schema::table('ijp_payments', fn (Blueprint $table) => $table->decimal('fee_rate', 7, 4)->change());
        Schema::table('ijp_fee_rate_changes', function (Blueprint $table) {
            $table->decimal('old_value', 7, 4)->nullable()->change();
            $table->decimal('new_value', 7, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ijp_business_profiles', fn (Blueprint $table) => $table->decimal('fee_percentage', 5, 2)->nullable()->change());
        Schema::table('ijp_payments', fn (Blueprint $table) => $table->decimal('fee_rate', 5, 2)->change());
        Schema::table('ijp_fee_rate_changes', function (Blueprint $table) {
            $table->decimal('old_value', 5, 2)->nullable()->change();
            $table->decimal('new_value', 5, 2)->nullable()->change();
        });
    }
};
