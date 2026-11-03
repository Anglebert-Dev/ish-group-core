<?php

namespace Modules\InjongePay\Services;

use Modules\InjongePay\Models\FeeRateChange;
use Modules\InjongePay\Support\PlatformSettings;

/**
 * The platform-wide fee and bank cut. Only future payments use a new rate;
 * every change goes into the fee history.
 */
class FeeSettings
{
    public function update(string $fee, string $bankCut): void
    {
        foreach ([[FeeRateChange::GLOBAL_FEE, 'fee_percentage', PlatformSettings::feePercentage(), $fee],
            [FeeRateChange::GLOBAL_BANK_CUT, 'bank_cut_percentage', PlatformSettings::bankCutPercentage(), $bankCut]] as [$scope, $key, $old, $new]) {
            $new = bcadd($new, '0', 2);

            if (bccomp($old, $new, 2) === 0) {
                continue;
            }

            PlatformSettings::set($key, $new);
            FeeRateChange::query()->create([
                'scope' => $scope, 'old_value' => $old, 'new_value' => $new,
                'changed_by' => auth()->id(), 'changed_by_name' => auth()->user()?->name,
            ]);
            audit('injongepay_fee_changed', __('injongepay::injongepay.audit_global_fee', ['what' => __('injongepay::injongepay.'.$key), 'old' => $old, 'new' => $new]),
                null, ['scope' => $scope, 'old' => $old, 'new' => $new]);
        }
    }
}
