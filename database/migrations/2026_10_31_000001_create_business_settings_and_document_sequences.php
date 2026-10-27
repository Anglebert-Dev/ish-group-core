<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-business configuration for the core and modules ("inventory.units", ...).
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'key']);
        });

        // One counter per business, document type and year. Only ever goes up.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 60);
            $table->unsignedSmallInteger('year')->default(0);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('business_settings');
    }
};
