<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A zip for customers who keep developing their copy: tests, guides,
        // demo data and the developer packages stay in.
        Schema::table('distribution_builds', function (Blueprint $table) {
            $table->boolean('developer')->default(false)->after('modules');
        });

        // The customer marked the module as customised: updates wait for them.
        Schema::table('distribution_site_modules', function (Blueprint $table) {
            $table->boolean('held')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_builds', fn (Blueprint $table) => $table->dropColumn('developer'));
        Schema::table('distribution_site_modules', fn (Blueprint $table) => $table->dropColumn('held'));
    }
};
