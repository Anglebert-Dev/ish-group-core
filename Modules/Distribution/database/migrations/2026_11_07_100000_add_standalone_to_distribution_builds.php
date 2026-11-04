<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A zip handed over for good: no token, no address of ours, no updates.
        Schema::table('distribution_builds', function (Blueprint $table) {
            $table->boolean('standalone')->default(false)->after('developer');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_builds', fn (Blueprint $table) => $table->dropColumn('standalone'));
    }
};
