<?php

namespace App\Support\Documents;

use App\Models\Business\Business;
use App\Models\Business\DocumentSequence;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\DB;

/**
 * Document numbers per business and type, e.g. "SO-2026-00042".
 *
 *   $order->number = app(DocumentNumber::class)->next('sales_order', 'SO');
 *
 * Call it inside the transaction that saves the document: the counter row
 * stays locked until it commits, so two users never get the same number,
 * and a number is never reused (counters only go up).
 *
 * Each business can change the format in its settings:
 *   numbering.<type>.prefix   (default: the $prefix given)
 *   numbering.<type>.yearly   (default true: restart at 1 each year, year in the number)
 *   numbering.<type>.digits   (default 5)
 */
class DocumentNumber
{
    public function __construct(
        protected BusinessSettings $settings,
        protected CurrentBusiness $current,
    ) {
    }

    public function next(string $type, string $prefix, ?Business $business = null): string
    {
        $businessId = $business?->id ?? $this->current->id()
            ?? throw new \LogicException('DocumentNumber needs a business.');
        $business ??= $this->current->get();

        $prefix = (string) $this->settings->get("numbering.{$type}.prefix", $prefix, $business);
        $yearly = (bool) $this->settings->get("numbering.{$type}.yearly", true, $business);
        $digits = max(1, min(12, (int) $this->settings->get("numbering.{$type}.digits", 5, $business)));
        $year = $yearly ? (int) now()->format('Y') : 0;

        $number = DB::transaction(function () use ($businessId, $type, $year) {
            DocumentSequence::query()->insertOrIgnore([
                'business_id' => $businessId, 'type' => $type, 'year' => $year,
                'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where(['business_id' => $businessId, 'type' => $type, 'year' => $year])
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return $sequence->last_number;
        });

        return collect([$prefix, $yearly ? $year : null, str_pad((string) $number, $digits, '0', STR_PAD_LEFT)])
            ->filter(fn ($part) => filled($part))
            ->implode('-');
    }
}
