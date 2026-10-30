<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finances are private per user: every row gets an owner. Existing data
 * goes to the owner of its business.
 */
return new class extends Migration
{
    private const TABLES = [
        'sav_accounts', 'sav_categories', 'sav_obligations', 'sav_transactions', 'sav_entries', 'sav_lines',
        'sav_obligation_items', 'sav_budgets', 'sav_goals', 'sav_goal_allocations', 'sav_recurring_rules',
        'sav_expected', 'sav_commitments', 'sav_reconciliations', 'sav_import_batches', 'sav_alerts_sent',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('owner_id')->nullable()->after('business_id');
                $t->index(['business_id', 'owner_id']);
            });

            DB::table($table)->whereNull('owner_id')->update([
                'owner_id' => DB::raw('(SELECT owner_id FROM businesses WHERE businesses.id = '.$table.'.business_id)'),
            ]);
        }

        // Alerts are remembered per person; budgets per person and category.
        Schema::table('sav_alerts_sent', function (Blueprint $t) {
            $t->dropUnique(['business_id', 'key']);
            $t->unique(['business_id', 'owner_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('sav_alerts_sent', function (Blueprint $t) {
            $t->dropUnique(['business_id', 'owner_id', 'key']);
            $t->unique(['business_id', 'key']);
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['business_id', 'owner_id']);
                $t->dropColumn('owner_id');
            });
        }
    }
};
