<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep each zip's install code (encrypted) so it can be emailed to the
 * customer with the download link, and record when it was emailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_builds', function (Blueprint $table) {
            $table->text('install_code')->nullable()->after('install_code_hash');
            $table->string('emailed_to')->nullable()->after('expires_at');
            $table->timestamp('emailed_at')->nullable()->after('emailed_to');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_builds', function (Blueprint $table) {
            $table->dropColumn(['install_code', 'emailed_to', 'emailed_at']);
        });
    }
};
