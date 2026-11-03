<?php

namespace Modules\InjongePay\Services;

use App\Models\Business\Business;
use App\Models\System\System;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\Wallet;

/**
 * The platform's own wallets, held by the operator (primary) business: the
 * bank's cut of fees and the platform's revenue. Created on first use.
 */
class InternalAccounts
{
    public function __construct(protected MerchantCodes $codes)
    {
    }

    public function bankCut(): Wallet
    {
        return $this->wallet(Wallet::ROLE_BANK_CUT);
    }

    public function platformRevenue(): Wallet
    {
        return $this->wallet(Wallet::ROLE_PLATFORM_REVENUE);
    }

    public function wallet(string $role): Wallet
    {
        $existing = Wallet::query()->withoutGlobalScopes()->where('internal_role', $role)->first();

        if ($existing) {
            return $existing;
        }

        $operator = Business::query()->find((int) System::getProperty('primary_business_id'));

        if (! $operator) {
            throw new InjongePayException(__('injongepay::injongepay.err_no_operator'));
        }

        return DB::transaction(function () use ($operator, $role) {
            $profile = BusinessProfile::for($operator);
            if (! $profile->exists) {
                $profile->is_internal = true;
                $profile->save();
            }

            $wallet = new Wallet([
                'name' => __('injongepay::injongepay.internal_wallet_'.$role),
                'is_enabled' => true,
                'bank_status' => Wallet::BANK_NOT_REGISTERED,
            ]);
            $wallet->forceFill([
                'business_id' => $operator->id,
                'internal_role' => $role,
                'merchant_code' => $this->codes->nextInternal(),
            ])->save();

            return $wallet;
        });
    }
}
