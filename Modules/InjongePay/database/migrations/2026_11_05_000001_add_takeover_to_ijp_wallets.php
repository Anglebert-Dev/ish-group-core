<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A code GTBank already had: its status there while staff decide, and the
     * moment we took it over. Transactions from before that aren't ours to credit.
     */
    public function up(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->string('bank_reported_status', 20)->nullable()->after('bank_status');
            $table->timestamp('live_from')->nullable()->after('bank_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->dropColumn(['bank_reported_status', 'live_from']);
        });
    }
};
