<?php

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extras', function (Blueprint $table) {
            $table->string('type')->default(ExtraType::Optional->value)->after('price');
        });

        $exists = DB::table('extras')
            ->where('type', ExtraType::Chauffeur->value)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            DB::table('extras')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Chauffeur service',
                'description' => 'Professional driver billed per rental day when the customer selects With Driver.',
                'price' => 25000,
                'type' => ExtraType::Chauffeur->value,
                'status' => ExtraStatus::Active->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('extras', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
