<?php

namespace Modules\InjongePay\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What GTBank needs to know about a business (KYC) and its fee. The business's
 * name and type come from the core Business.
 */
class BusinessProfile extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'ijp_business_profiles';

    protected $guarded = ['id', 'uuid', 'business_id', 'legacy_id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'fee_percentage' => 'decimal:2', 'limit_per_transaction' => 'integer', 'limit_daily' => 'integer',
            'limit_monthly' => 'integer', 'step_up_above' => 'integer', 'second_approval_above' => 'integer'];
    }

    public function supportStaff(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'support_staff_id');
    }

    /** The first KYC field GTBank needs that is still empty, as a label; null when complete. */
    public function missingKyc(): ?string
    {
        foreach (['tin' => 'kyc_tin', 'contact_person' => 'kyc_contact_person', 'contact_phone' => 'kyc_contact_phone', 'contact_email' => 'kyc_contact_email'] as $field => $label) {
            if (blank($this->{$field})) {
                return __('injongepay::injongepay.'.$label);
            }
        }

        return null;
    }

    public static function for(Business $business): self
    {
        return self::query()->withoutGlobalScope('business')->where('business_id', $business->id)->first()
            ?? (new self)->forceFill(['business_id' => $business->id]);
    }
}
