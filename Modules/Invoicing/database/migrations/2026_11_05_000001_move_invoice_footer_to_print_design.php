<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The invoice footer moved from Invoicing settings to the print design
 * (print.types.sales_invoice.footer). Copied once; nothing is overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('business_settings')->where('key', 'invoicing.footer')->get() as $row) {
            $footer = json_decode((string) $row->value, true);

            if (! is_string($footer) || trim($footer) === '') {
                continue;
            }

            $current = DB::table('business_settings')->where('business_id', $row->business_id)->where('key', 'print.types')->first();
            $types = $current ? (array) json_decode((string) $current->value, true) : [];

            if (! empty($types['sales_invoice']['footer'])) {
                continue;
            }

            $types['sales_invoice'] = array_merge($types['sales_invoice'] ?? [], ['footer' => $footer]);

            DB::table('business_settings')->updateOrInsert(
                ['business_id' => $row->business_id, 'key' => 'print.types'],
                ['value' => json_encode($types), 'updated_at' => now(), 'created_at' => $current->created_at ?? now()],
            );
        }
    }

    public function down(): void
    {
        // Nothing to undo: invoicing.footer was left in place.
    }
};
