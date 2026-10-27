<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('actual_return_at')->nullable()->after('return_at');
            $table->decimal('extra_days_charge', 12, 2)->nullable()->after('total_amount');
            $table->decimal('outstanding_balance', 12, 2)->nullable()->after('extra_days_charge');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['actual_return_at', 'extra_days_charge', 'outstanding_balance']);
        });
    }
};
