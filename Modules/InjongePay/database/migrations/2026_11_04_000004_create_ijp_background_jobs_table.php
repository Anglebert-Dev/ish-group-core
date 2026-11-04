<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Each scheduled job: its on/off and timing as set on screen, and how its last run went. */
    public function up(): void
    {
        Schema::create('ijp_background_jobs', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->boolean('is_enabled')->default(true);
            $table->string('timing', 10)->nullable(); // minutes ("10") or a time of day ("01:30")
            $table->string('last_status', 10)->nullable(); // running | ok | failed
            $table->boolean('failing')->default(false); // survives the next run's "running", so the alert goes once
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_background_jobs');
    }
};
