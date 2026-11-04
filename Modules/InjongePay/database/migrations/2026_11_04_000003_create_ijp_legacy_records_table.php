<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows of the old app that have no table here (refunds, OTP history,
     * logins, notifications) and the original user, institution and wallet
     * rows, kept as they were. Secrets are removed before they are stored.
     */
    public function up(): void
    {
        Schema::create('ijp_legacy_records', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 60);
            $table->string('legacy_id', 64)->nullable();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->json('data');
            $table->timestamp('created_at')->nullable();

            $table->index(['source_table', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_legacy_records');
    }
};
