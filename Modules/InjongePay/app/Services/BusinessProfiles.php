<?php

namespace Modules\InjongePay\Services;

use App\Models\Business\Business;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\FeeRateChange;
use Modules\InjongePay\Support\Counter;

/**
 * KYC and fee of a business, kept by the platform. Fee changes are written to
 * the fee history; everything else is audited through the model.
 */
class BusinessProfiles
{
    public const FIELDS = ['short_name', 'tin', 'country', 'city', 'address', 'contact_person', 'contact_phone', 'contact_email', 'fee_percentage', 'support_staff_id'];

    // Entered in RWF, stored in cents; blank = no limit.
    public const LIMITS = ['limit_per_transaction', 'limit_daily', 'limit_monthly', 'step_up_above', 'step_up_hourly_above', 'second_approval_above'];

    public function save(Business $business, array $data): BusinessProfile
    {
        return DB::transaction(function () use ($business, $data) {
            $profile = BusinessProfile::for($business);
            $oldFee = $profile->exists ? $profile->fee_percentage : null;
            $oldLimits = $profile->only(self::LIMITS);

            $profile->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $profile->fee_percentage = isset($data['fee_percentage']) && $data['fee_percentage'] !== '' ? \Modules\InjongePay\Support\Rate::normalise((string) $data['fee_percentage']) : null;
            foreach (self::LIMITS as $limit) {
                if (array_key_exists($limit, $data)) {
                    $profile->{$limit} = $data[$limit] === null || $data[$limit] === '' ? null : \Modules\InjongePay\Support\Money::toCents((string) $data[$limit]);
                }
            }
            $profile->business_id = $business->id;
            $profile->code ??= 'INJP'.now()->format('Ymd').str_pad((string) Counter::next('business_code'), 4, '0', STR_PAD_LEFT);
            $profile->save();

            if (array_diff_assoc(array_map('strval', $profile->only(self::LIMITS)), array_map('strval', $oldLimits)) !== []) {
                \Modules\InjongePay\Support\BusinessAlert::send($business->id, __('injongepay::injongepay.alert_limits'),
                    __('injongepay::injongepay.alert_limits_body'), route('injongepay.profile', [], false));
            }

            if ($this->rateChanged($oldFee, $profile->fee_percentage)) {
                FeeRateChange::query()->create([
                    'scope' => FeeRateChange::BUSINESS_FEE,
                    'business_id' => $business->id,
                    'old_value' => $oldFee,
                    'new_value' => $profile->fee_percentage,
                    'changed_by' => auth()->id(),
                    'changed_by_name' => auth()->user()?->name,
                ]);

                audit('injongepay_fee_changed', __('injongepay::injongepay.audit_business_fee', [
                    'business' => $business->name, 'old' => \Modules\InjongePay\Support\Rate::display($oldFee), 'new' => \Modules\InjongePay\Support\Rate::display($profile->fee_percentage),
                ]), $profile, ['old' => $oldFee, 'new' => $profile->fee_percentage], $business->id);
            }

            return $profile;
        });
    }

    private function rateChanged(mixed $old, mixed $new): bool
    {
        return ! \Modules\InjongePay\Support\Rate::same($old === null ? null : (string) $old, $new === null ? null : (string) $new);
    }
}
