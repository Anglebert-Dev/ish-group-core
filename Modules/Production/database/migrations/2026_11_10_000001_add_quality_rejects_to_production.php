<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pieces that failed quality control: counted, never put into stock.
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->decimal('rejected', 15, 3)->default(0)->after('counts');
        });

        Schema::table('production_entries', function (Blueprint $table) {
            $table->string('kind', 10)->default('count')->after('stage_name');
        });
    }

    public function down(): void
    {
        Schema::table('production_entries', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('production_work_orders', fn (Blueprint $table) => $table->dropColumn('rejected'));
    }
};
