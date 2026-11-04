<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When GTBank first said it has no record of a live code, until a second answer settles it. */
    public function up(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->timestamp('bank_missing_since')->nullable()->after('bank_error_at');
        });
    }

    public function down(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->dropColumn('bank_missing_since');
        });
    }
};
