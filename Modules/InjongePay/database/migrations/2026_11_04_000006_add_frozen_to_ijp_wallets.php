<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Frozen: money keeps coming in, nothing leaves, until a person looks. Not the same as switched off. */
    public function up(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->timestamp('frozen_at')->nullable()->after('is_enabled');
            $table->string('frozen_reason')->nullable()->after('frozen_at');
        });
    }

    public function down(): void
    {
        Schema::table('ijp_wallets', function (Blueprint $table) {
            $table->dropColumn(['frozen_at', 'frozen_reason']);
        });
    }
};
