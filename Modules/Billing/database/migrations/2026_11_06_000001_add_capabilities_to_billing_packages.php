<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extras each package includes (App\Support\Entitlements\Capabilities).
        // Null = a package made before: everything allowed.
        Schema::table('billing_packages', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('billing_packages', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};
