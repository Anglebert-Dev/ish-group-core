<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support & updates end date (docs/self-hosting-build-guide.md §11).
        // Null = no end date (sites made before licences existed).
        Schema::table('distribution_sites', function (Blueprint $table) {
            $table->date('support_until')->nullable()->after('status');
            // Last reminder sent ("30", "7", "0") and for which end date, so each goes once.
            $table->string('support_reminder_stage', 4)->nullable()->after('support_until');
            $table->date('support_reminded_for')->nullable()->after('support_reminder_stage');
        });

        // What a self-hosting customer is charged: the licence (once) and support
        // renewals (yearly). Paying a charge with a period extends support.
        Schema::create('distribution_charges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('kind', 16);                 // licence | support
            $table->string('description');
            $table->json('modules')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('period_from')->nullable();
            $table->date('period_until')->nullable();
            $table->string('status', 16);               // unpaid | paid | cancelled
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_charges');
        Schema::table('distribution_sites', function (Blueprint $table) {
            $table->dropColumn(['support_until', 'support_reminder_stage', 'support_reminded_for']);
        });
    }
};
