<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every call to or from GTBank, with secrets redacted, linked to what it was about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ijp_bank_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('direction', 10); // outbound | inbound
            $table->string('method', 8);
            $table->string('endpoint', 120);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // A reference to find the call by: merchant code, cash-out or transaction reference.
            $table->string('reference', 64)->nullable();
            $table->json('request')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response')->nullable();
            // ok | not_found | rejected | unknown
            $table->string('outcome', 12);
            $table->string('bank_code', 10)->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('caller', 120)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['reference']);
            $table->index(['endpoint', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_bank_calls');
    }
};
