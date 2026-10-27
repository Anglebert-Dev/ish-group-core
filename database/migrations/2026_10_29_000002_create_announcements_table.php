<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superadmin → businesses messages (Communicator).
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('audience');                 // all | active | selected
            $table->json('business_ids')->nullable();
            $table->boolean('send_email')->default(true);
            $table->boolean('send_in_app')->default(true);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
