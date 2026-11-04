<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The last line of defence against paying twice: the database itself
     * refuses a form sent twice, or an approval that runs twice.
     */
    public function up(): void
    {
        Schema::table('ijp_approvals', function (Blueprint $table) {
            $table->string('request_token', 36)->nullable()->unique()->after('wallet_id');
        });
        Schema::table('ijp_cashouts', fn (Blueprint $table) => $table->unique('approval_id'));
        Schema::table('ijp_transfers', fn (Blueprint $table) => $table->unique('approval_id'));
    }

    public function down(): void
    {
        Schema::table('ijp_transfers', fn (Blueprint $table) => $table->dropUnique(['approval_id']));
        Schema::table('ijp_cashouts', fn (Blueprint $table) => $table->dropUnique(['approval_id']));
        Schema::table('ijp_approvals', function (Blueprint $table) {
            $table->dropUnique(['request_token']);
            $table->dropColumn('request_token');
        });
    }
};
