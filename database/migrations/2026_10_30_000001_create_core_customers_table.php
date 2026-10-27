<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers belong to the core. Databases that already have the table
 * (created earlier by the Frontend module) only get the new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
                $table->string('type')->default('person');
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('tax_id')->nullable();
                $table->text('address')->nullable();
                $table->text('delivery_address')->nullable();
                $table->unsignedSmallInteger('payment_terms_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->string('status')->default('active');
                $table->rememberToken();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->unsignedBigInteger('insert_by')->nullable();
                $table->unsignedBigInteger('update_by')->nullable();
                $table->unsignedBigInteger('delete_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'email']);
                $table->index(['business_id', 'status']);
            });

            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach ([
                'type' => fn () => $table->string('type')->default('person')->after('business_id'),
                'company_name' => fn () => $table->string('company_name')->nullable()->after('name'),
                'contact_person' => fn () => $table->string('contact_person')->nullable()->after('company_name'),
                'tax_id' => fn () => $table->string('tax_id')->nullable()->after('phone'),
                'address' => fn () => $table->text('address')->nullable()->after('tax_id'),
                'delivery_address' => fn () => $table->text('delivery_address')->nullable()->after('address'),
                'payment_terms_days' => fn () => $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('delivery_address'),
                'notes' => fn () => $table->text('notes')->nullable()->after('payment_terms_days'),
            ] as $column => $add) {
                if (! Schema::hasColumn('customers', $column)) {
                    $add();
                }
            }

            // Many clients have no email.
            $table->string('email')->nullable()->change();
        });

        if (Schema::hasIndex('customers', 'customers_email_unique')) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropUnique('customers_email_unique'));
        }

        if (! Schema::hasIndex('customers', 'customers_business_id_email_unique')) {
            Schema::table('customers', fn (Blueprint $table) => $table->unique(['business_id', 'email']));
        }
    }

    public function down(): void
    {
        // Keep customer data: dropping it would also break the website module.
    }
};
