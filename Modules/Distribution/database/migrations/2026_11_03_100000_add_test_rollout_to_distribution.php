<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test sites get new releases first: a release published "to test sites
 * first" is offered only to sites marked as test, until it is released
 * to everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_sites', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('status');
        });

        Schema::table('distribution_releases', function (Blueprint $table) {
            $table->boolean('test_only')->default(false)->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_releases', fn (Blueprint $table) => $table->dropColumn('test_only'));
        Schema::table('distribution_sites', fn (Blueprint $table) => $table->dropColumn('is_test'));
    }
};
