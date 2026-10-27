<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One website per business, reached at /s/{slug}.
        Schema::create('frontend_sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(false);
            // Only what the business changed; the rest comes from config defaults.
            $table->json('content')->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Customers belong to one business's site: the same email may sign
        // up on several sites.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['business_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'email']);
            $table->unique('email');
        });

        Schema::dropIfExists('frontend_sites');
    }
};
