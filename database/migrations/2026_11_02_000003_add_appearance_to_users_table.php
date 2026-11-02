<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The user's own light/dark/system choice; null = their business's mode.
        // Kept on the user, never on the business (docs/branding.md §2.6).
        Schema::table('users', function (Blueprint $table) {
            $table->string('appearance', 10)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
