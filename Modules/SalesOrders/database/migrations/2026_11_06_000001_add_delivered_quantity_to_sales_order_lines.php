<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('delivered_quantity', 15, 3)->default(0)->after('quantity');
        });

        // Orders delivered before part deliveries existed were delivered in full.
        DB::table('sales_order_lines')
            ->whereIn('order_id', DB::table('sales_orders')->where('status', 'delivered')->select('id'))
            ->update(['delivered_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', fn (Blueprint $table) => $table->dropColumn('delivered_quantity'));
    }
};
