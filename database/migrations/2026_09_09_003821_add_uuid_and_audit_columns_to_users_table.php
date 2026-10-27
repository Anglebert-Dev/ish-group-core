<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('insert_by')->nullable()->after('status');
            $table->unsignedBigInteger('update_by')->nullable()->after('insert_by');
            $table->unsignedBigInteger('delete_by')->nullable()->after('update_by');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'insert_by', 'update_by', 'delete_by', 'deleted_at']);
        });
    }
};
