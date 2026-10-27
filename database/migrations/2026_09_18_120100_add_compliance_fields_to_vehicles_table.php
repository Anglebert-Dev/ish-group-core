<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('insurance_company')->nullable()->after('notes');
            $table->date('insurance_expires_at')->nullable()->after('insurance_company');
            $table->string('insurance_document')->nullable()->after('insurance_expires_at');

            $table->date('inspection_done_at')->nullable()->after('insurance_document');
            $table->date('inspection_expires_at')->nullable()->after('inspection_done_at');
            $table->string('inspection_document')->nullable()->after('inspection_expires_at');

            $table->string('carte_jaune_number')->nullable()->after('inspection_document');
            $table->date('carte_jaune_issued_at')->nullable()->after('carte_jaune_number');
            $table->string('carte_jaune_document')->nullable()->after('carte_jaune_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_company',
                'insurance_expires_at',
                'insurance_document',
                'inspection_done_at',
                'inspection_expires_at',
                'inspection_document',
                'carte_jaune_number',
                'carte_jaune_issued_at',
                'carte_jaune_document',
            ]);
        });
    }
};
