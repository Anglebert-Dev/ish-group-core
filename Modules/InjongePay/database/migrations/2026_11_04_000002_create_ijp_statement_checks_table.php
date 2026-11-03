<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** GTBank's statement of the platform's settlement account, compared with our books. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ijp_statement_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('file_name');
            $table->unsignedInteger('lines')->default(0);
            $table->json('summary');
            $table->json('unmatched');
            $table->boolean('agrees')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijp_statement_checks');
    }
};
