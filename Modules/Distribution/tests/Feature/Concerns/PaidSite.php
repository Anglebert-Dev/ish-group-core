<?php

namespace Modules\Distribution\Tests\Feature\Concerns;

use Illuminate\Support\Str;
use Modules\Distribution\Enums\ChargeStatus;
use Modules\Distribution\Models\Charge;
use Modules\Distribution\Models\Site;

/**
 * A customer who has paid: licence paid, a year of support. Zips need a paid
 * licence; updates need support (docs/self-hosting-build-guide.md §13).
 */
trait PaidSite
{
    protected function paid(Site $site): Site
    {
        $charge = new Charge(['kind' => Charge::LICENCE, 'description' => 'Licence', 'amount' => 100, 'issued_on' => today()]);
        $charge->forceFill(['site_id' => $site->id, 'number' => 'LIC-T-'.Str::random(8), 'status' => ChargeStatus::Paid, 'paid_on' => today()])->save();
        $site->forceFill(['support_until' => today()->addYear()])->save();

        return $site->fresh();
    }
}
