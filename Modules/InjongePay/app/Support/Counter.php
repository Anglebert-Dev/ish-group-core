<?php

namespace Modules\InjongePay\Support;

use Illuminate\Support\Facades\DB;

/**
 * Collision-free numbers: one locked row per key, committed on its own. Take
 * the number before the money transaction starts so payments never wait on the
 * counter row; a number whose record then fails is simply skipped.
 */
final class Counter
{
    public static function next(string $key, int $startAfter = 0): int
    {
        return DB::transaction(function () use ($key, $startAfter) {
            DB::table('ijp_counters')->insertOrIgnore(['key' => $key, 'value' => $startAfter, 'created_at' => now(), 'updated_at' => now()]);

            $row = DB::table('ijp_counters')->where('key', $key)->lockForUpdate()->first();
            $next = max((int) $row->value, $startAfter) + 1;

            DB::table('ijp_counters')->where('key', $key)->update(['value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    /**
     * PAY20261103000042: prefix, date, then a daily counter. Letters and digits only,
     * at most 35 characters (GTBank's rule for references).
     */
    public static function reference(string $prefix): string
    {
        $day = now()->format('Ymd');

        return $prefix.$day.str_pad((string) self::next("ref:{$prefix}:{$day}"), 6, '0', STR_PAD_LEFT);
    }
}
