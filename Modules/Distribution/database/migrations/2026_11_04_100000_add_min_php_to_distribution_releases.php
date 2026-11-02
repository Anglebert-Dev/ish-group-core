<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core releases carry the lowest PHP version they run on, so a site on an
 * older PHP refuses them before touching anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_releases', fn (Blueprint $table) => $table->string('min_php')->nullable()->after('min_core_version'));
    }

    public function down(): void
    {
        Schema::table('distribution_releases', fn (Blueprint $table) => $table->dropColumn('min_php'));
    }
};
