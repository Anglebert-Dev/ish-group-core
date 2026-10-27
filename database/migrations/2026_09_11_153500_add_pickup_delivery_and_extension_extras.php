<?php

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Enums\PickupMethod;
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
            $table->string('pickup_method')->default(PickupMethod::Station->value)->after('return_station_id');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('chauffeur_fee');
            $table->string('delivery_address', 500)->nullable()->after('delivery_fee');
        });

        $this->ensureTypedExtra(ExtraType::Delivery, 'Vehicle delivery', 'Flat fee to deliver the vehicle to the customer\'s address.', 15000);
        $this->ensureTypedExtra(ExtraType::Extension, 'Extension surcharge', 'Extra charge per billable day when a customer extends beyond the booked return time (added on top of the normal daily rate).', 10000);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_method', 'delivery_fee', 'delivery_address']);
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
