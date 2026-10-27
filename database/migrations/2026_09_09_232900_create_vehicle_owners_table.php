<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_owners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('national_id')->nullable()->unique();
            $table->text('notes')->nullable();

            $table->decimal('owner_share_percent', 5, 2);
            $table->decimal('company_share_percent', 5, 2);
            $table->date('agreement_starts_on')->nullable();
            $table->date('agreement_ends_on')->nullable();

            $table->string('status')->default('active');
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_owners');
    }
};
