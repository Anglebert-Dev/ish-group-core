<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->date('transaction_date');
            $table->string('reference')->unique();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->string('status')->default('posted');
            $table->foreignId('reversed_ledger_id')->nullable()->constrained('general_ledgers')->nullOnDelete();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reference_type', 'reference_id']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
    }
};
