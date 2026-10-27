<?php

use App\Libraries\UuidLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Route model binding uses uuid. Older rows (or seed users created before
     * the uuid column existed) can still be null and break URL generation.
     */
    public function up(): void
    {
        foreach (['users', 'customers'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->pluck('id')
                ->each(function (int $id) use ($table): void {
                    DB::table($table)->where('id', $id)->update([
                        'uuid' => UuidLibrary::generate(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        //
    }
};
