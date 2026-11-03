<?php

namespace Modules\InjongePay\Services;

use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;
use Modules\InjongePay\Support\PlatformSettings;
use Modules\InjongePay\Support\Rate;

/**
 * How a payment splits: the business keeps the net, the fee is shared between
 * the bank (bank cut) and the platform. Same rounding as production, in cents.
 */
class Fees
{
    /**
     * @return array{rate: string, fee: int, bank_cut: int, platform: int, net: int}
     */
    public function split(Wallet $wallet, int $gross): array
    {
        $rate = $this->rateFor($wallet->business_id);
        $fee = Money::percentOf($gross, $rate);
        $net = $gross - $fee;

        if ($net <= 0) {
            throw new InjongePayException(__('injongepay::injongepay.err_net_not_positive'));
        }

        $bankCut = Money::percentOf($fee, PlatformSettings::bankCutPercentage());

        return ['rate' => $rate, 'fee' => $fee, 'bank_cut' => $bankCut, 'platform' => $fee - $bankCut, 'net' => $net];
    }

    public function rateFor(int $businessId): string
    {
        $own = BusinessProfile::query()->withoutGlobalScope('business')->where('business_id', $businessId)->value('fee_percentage');

        return $own !== null ? Rate::normalise((string) $own) : PlatformSettings::feePercentage();
    }
}
