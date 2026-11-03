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

    public function save(Business $business, array $data): BusinessProfile
    {
        return DB::transaction(function () use ($business, $data) {
            $profile = BusinessProfile::for($business);
            $oldFee = $profile->exists ? $profile->fee_percentage : null;

            $profile->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $profile->fee_percentage = isset($data['fee_percentage']) && $data['fee_percentage'] !== '' ? bcadd((string) $data['fee_percentage'], '0', 2) : null;
            $profile->business_id = $business->id;
            $profile->code ??= 'INJP'.now()->format('Ymd').str_pad((string) Counter::next('business_code'), 4, '0', STR_PAD_LEFT);
            $profile->save();

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
                    'business' => $business->name, 'old' => $oldFee ?? '—', 'new' => $profile->fee_percentage ?? '—',
                ]), $profile, ['old' => $oldFee, 'new' => $profile->fee_percentage], $business->id);
            }

            return $profile;
        });
    }

    private function rateChanged(mixed $old, mixed $new): bool
    {
        if ($old === null || $new === null) {
            return $old !== $new;
        }

        return bccomp((string) $old, (string) $new, 2) !== 0;
    }
}
