<?php

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\ReturnMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('return_method')->default(ReturnMethod::Station->value)->after('pickup_method');
            $table->decimal('collection_fee', 12, 2)->default(0)->after('delivery_fee');
            $table->string('collection_address', 500)->nullable()->after('delivery_address');
        });

        $this->ensureTypedExtra(
            ExtraType::Collection,
            'Vehicle collection',
            'Flat fee to collect the vehicle from the customer\'s address at the end of the rental.',
            15000
        );
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['return_method', 'collection_fee', 'collection_address']);
        });
    }

    protected function ensureTypedExtra(ExtraType $type, string $name, string $description, float $price): void
    {
        $exists = DB::table('extras')
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('extras')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'type' => $type->value,
            'status' => ExtraStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
