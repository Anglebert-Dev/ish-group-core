@php
    $owner = $owner ?? null;
    $cancelHref = $cancelHref ?? route('fleet.owners.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.identity')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="first_name" :label="__('body.first_name')" :value="old('first_name', $owner?->first_name)" required />
        <x-common.input name="last_name" :label="__('body.last_name')" :value="old('last_name', $owner?->last_name)" required />
        <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $owner?->phone)" required />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $owner?->email)" />
        <x-common.input name="national_id" :label="__('body.national_id')" :value="old('national_id', $owner?->national_id)" required />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $owner?->status?->value ?? \App\Enums\VehicleOwnerStatus::Active->value)" :options="\App\Enums\VehicleOwnerStatus::cases()" :hide-placeholder="true" />
        <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $owner?->address)" :rows="2" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.income_agreement')">
    <p class="text-sm text-muted mb-5">{{ __('body.income_agreement_hint') }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input type="number" name="owner_share_percent" :label="__('body.owner_share_percent')" :value="old('owner_share_percent', $owner?->owner_share_percent)" required min="0" max="100" step="0.01" :hint="__('body.owner_share_percent_hint')" />
        <x-common.input type="number" name="company_share_percent" :label="__('body.company_share_percent')" :value="old('company_share_percent', $owner?->company_share_percent)" required min="0" max="100" step="0.01" :hint="__('body.company_share_percent_hint')" />
        <x-common.input type="date" name="agreement_starts_on" :label="__('body.agreement_starts_on')" :value="old('agreement_starts_on', $owner?->agreement_starts_on?->format('Y-m-d'))" />
        <x-common.input type="date" name="agreement_ends_on" :label="__('body.agreement_ends_on')" :value="old('agreement_ends_on', $owner?->agreement_ends_on?->format('Y-m-d'))" />
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $owner?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.first_name')" for="first_name" />
        <x-common.wizard.review-row :label="__('body.last_name')" for="last_name" />
        <x-common.wizard.review-row :label="__('body.phone')" for="phone" />
        <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
        <x-common.wizard.review-row :label="__('body.national_id')" for="national_id" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
        <x-common.wizard.review-row :label="__('body.owner_share_percent')" for="owner_share_percent" />
        <x-common.wizard.review-row :label="__('body.company_share_percent')" for="company_share_percent" />
        <x-common.wizard.review-row :label="__('body.agreement_starts_on')" for="agreement_starts_on" />
        <x-common.wizard.review-row :label="__('body.agreement_ends_on')" for="agreement_ends_on" />
        <x-common.wizard.review-row :label="__('body.notes')" for="notes" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>

@push('scripts')
<script>
    (function () {
        var ownerShare = document.getElementById('field-owner-share-percent');
        var companyShare = document.getElementById('field-company-share-percent');
        if (!ownerShare || !companyShare) return;

        function clampPercent(value) {
            if (value === '' || value === null || typeof value === 'undefined') {
                return null;
            }
            var number = parseFloat(value);
            if (isNaN(number)) {
                return null;
            }
            if (number < 0) number = 0;
            if (number > 100) number = 100;
            return Math.round(number * 100) / 100;
        }

        function formatPercent(value) {
            return Number.isInteger(value) ? String(value) : String(value);
        }

        function syncFrom(source, target) {
            var clamped = clampPercent(source.value);
            if (clamped === null) {
                return;
            }
            if (String(source.value) !== formatPercent(clamped)) {
                source.value = formatPercent(clamped);
            }
            target.value = formatPercent(Math.round((100 - clamped) * 100) / 100);
        }

        ownerShare.setAttribute('min', '0');
        ownerShare.setAttribute('max', '100');
        companyShare.setAttribute('min', '0');
        companyShare.setAttribute('max', '100');

        ownerShare.addEventListener('input', function () { syncFrom(ownerShare, companyShare); });
        companyShare.addEventListener('input', function () { syncFrom(companyShare, ownerShare); });
        ownerShare.addEventListener('blur', function () { syncFrom(ownerShare, companyShare); });
        companyShare.addEventListener('blur', function () { syncFrom(companyShare, ownerShare); });
    })();
</script>
@endpush
