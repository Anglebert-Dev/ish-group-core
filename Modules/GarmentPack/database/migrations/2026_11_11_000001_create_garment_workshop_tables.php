<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table) {
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        };

        // Pay per piece for each stage of a style: {"Sewing": 300, …}
        Schema::table('garment_styles', function (Blueprint $table) {
            $table->json('piece_rates')->nullable()->after('overhead_cost');
        });

        Schema::create('garment_workers', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('line', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $audit($table);
        });

        Schema::create('garment_piece_work', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('garment_workers');
            $table->foreignId('work_order_id')->constrained('production_work_orders');
            $table->unsignedTinyInteger('stage');
            $table->string('stage_name', 60);
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->date('work_date');
            $table->timestamp('paid_at')->nullable();
            $audit($table);

            $table->index(['business_id', 'work_date']);
        });

        // Cut-make-trim: a stage done by an outside tailor.
        Schema::create('garment_cmt_jobs', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('work_order_id')->constrained('production_work_orders');
            $table->string('subcontractor');
            $table->string('phone', 40)->nullable();
            $table->unsignedTinyInteger('stage');
            $table->string('stage_name', 60);
            $table->decimal('sent_quantity', 15, 3);
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->date('sent_on');
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('sent');
            $table->text('notes')->nullable();
            $audit($table);

            $table->unique(['business_id', 'number']);
        });

        Schema::create('garment_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('production_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->unique(['work_order_id', 'number']);
        });

        Schema::create('garment_measurements', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('name', 120);
            $table->json('values');
            $table->date('taken_on')->nullable();
            $table->text('notes')->nullable();
            $audit($table);
        });
    }

    public function down(): void
    {
        foreach (['garment_measurements', 'garment_bundles', 'garment_cmt_jobs', 'garment_piece_work', 'garment_workers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('garment_styles', fn (Blueprint $table) => $table->dropColumn('piece_rates'));
    }
};
