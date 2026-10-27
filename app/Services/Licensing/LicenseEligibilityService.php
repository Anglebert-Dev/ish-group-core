<?php

namespace App\Services\Licensing;

use App\Enums\LicenceCategory;

/**
 * Driver-to-vehicle licence eligibility. Deliberately not a simple equality
 * check — see config/licensing.php for the actual coverage matrix.
 */
class LicenseEligibilityService
{
    public function isEligible(LicenceCategory $driverLicence, LicenceCategory $requiredCategory): bool
    {
        return in_array($requiredCategory->value, $this->coveredCategories($driverLicence), true);
    }

    /**
     * Every vehicle "required licence category" a holder of this licence may drive.
     *
     * @return list<string>
     */
    public function coveredCategories(LicenceCategory $driverLicence): array
    {
        return config("licensing.eligibility.{$driverLicence->value}", []);
    }
}
