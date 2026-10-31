<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the customer told us about their server (ServerDetails), typed in or
 * uploaded as CSV; used by builds and the setup guide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_sites', function (Blueprint $table) {
            $table->json('server_details')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_sites', function (Blueprint $table) {
            $table->dropColumn('server_details');
        });
    }
};
