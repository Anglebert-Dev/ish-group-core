{{-- Name and the KYC that overrides the business's for this merchant code. --}}
<x-common.input name="name" :label="__('body.name')" :value="old('name', $wallet?->name)" :hint="__('injongepay::injongepay.wallet_name_hint')" required />
<x-common.input name="kyc_business_owner" :label="__('injongepay::injongepay.kyc_contact_person')" :value="old('kyc_business_owner', $wallet?->kyc_business_owner)" :hint="__('injongepay::injongepay.override_hint')" />
<x-common.input name="kyc_tin" :label="__('injongepay::injongepay.kyc_tin')" :value="old('kyc_tin', $wallet?->kyc_tin)" :hint="__('injongepay::injongepay.override_hint')" inputmode="numeric" maxlength="9" />
<x-common.phone-input name="kyc_contact_phone" :label="__('injongepay::injongepay.kyc_contact_phone')" :value="old('kyc_contact_phone', $wallet?->kyc_contact_phone)" />
<x-common.input name="kyc_contact_email" type="email" :label="__('injongepay::injongepay.kyc_contact_email')" :value="old('kyc_contact_email', $wallet?->kyc_contact_email)" :hint="__('injongepay::injongepay.override_hint')" />
