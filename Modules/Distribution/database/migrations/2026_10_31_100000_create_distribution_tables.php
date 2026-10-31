<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level (no business_id): these describe OUR customers' servers.
 * docs/self-hosting-build-guide.md §6.
 */
return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->unsignedBigInteger('insert_by')->nullable();
        $table->unsignedBigInteger('update_by')->nullable();
        $table->unsignedBigInteger('delete_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }

    public function up(): void
    {
        // A customer's self-hosted copy.
        Schema::create('distribution_sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('customer_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('app_url')->nullable();
            $table->string('update_mode')->default('manual');   // auto | manual
            $table->string('status')->default('active');        // active | suspended
            $table->text('notes')->nullable();
            // Last reported by the site itself (check-in).
            $table->string('core_version')->nullable();
            $table->string('php_version')->nullable();
            $table->string('db_driver')->nullable();
            $table->boolean('modules_writable')->nullable();
            $table->timestamp('last_check_in_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('installed_at')->nullable();
            $this->audit($table);
        });

        // Bearer tokens a site uses for the update API. Only the hash is kept.
        Schema::create('distribution_site_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('last4', 4);
            $table->unsignedBigInteger('build_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Which modules a site gets (we decide), and what it last reported.
        Schema::create('distribution_site_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->string('module');
            $table->string('installed_version')->nullable();
            $table->string('status')->default('pending_install'); // pending_install | installed | update_available | failed
            $table->text('last_error')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'module']);
        });

        // A signed, published version of one module.
        Schema::create('distribution_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module');
            $table->string('version');
            $table->char('sha256', 64);
            $table->text('signature');              // base64 ed25519
            $table->unsignedBigInteger('size');
            $table->string('path');                 // on the private disk
            $table->json('requires')->nullable();
            $table->string('min_core_version')->nullable();
            $table->text('changelog')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('yanked_at')->nullable();
            $this->audit($table);
            $table->unique(['module', 'version']);
        });

        // A zip built for one site (phase 3).
        Schema::create('distribution_builds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('distribution_site_tokens')->nullOnDelete();
            $table->string('core_version')->nullable();
            $table->json('modules');
            $table->string('filename')->nullable();
            $table->string('path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('install_code_hash')->nullable();
            $table->string('status')->default('queued'); // queued | building | ready | failed | expired
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $this->audit($table);
        });

        // Every check-in from a site (phase 4); pruned after 90 days.
        Schema::create('distribution_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('core_version')->nullable();
            $table->json('reported')->nullable();
            $table->json('offered')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Result of each install/update on a site (phase 4/5).
        Schema::create('distribution_update_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('distribution_sites')->cascadeOnDelete();
            $table->string('module');
            $table->string('action');               // install | update
            $table->string('from_version')->nullable();
            $table->string('to_version');
            $table->string('status');               // success | failed | rolled_back
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_update_reports');
        Schema::dropIfExists('distribution_check_ins');
        Schema::dropIfExists('distribution_builds');
        Schema::dropIfExists('distribution_releases');
        Schema::dropIfExists('distribution_site_modules');
        Schema::dropIfExists('distribution_site_tokens');
        Schema::dropIfExists('distribution_sites');
    }
};
