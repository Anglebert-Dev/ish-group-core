<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('action');
            $table->string('slot');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['action', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_assignments');
    }
};
