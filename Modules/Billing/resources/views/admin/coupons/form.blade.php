@php
    $isNew = ! $coupon->exists;
    $selected = array_map('intval', old('package_ids', $coupon->package_ids ?? []));
@endphp
<x-layouts.app :title="$isNew ? __('billing::billing.add_coupon') : $coupon->code">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.coupons')" :description="$isNew ? __('billing::billing.add_coupon') : $coupon->code" />

    <form action="{{ $isNew ? route('superadmin.billing.coupons.store') : route('superadmin.billing.coupons.update', $coupon) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-8 space-y-6 max-w-4xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <x-common.input name="code" :label="__('billing::billing.code')" :value="old('code', $coupon->code)" :hint="__('billing::billing.code_hint')" required />
            <x-common.input name="description" :label="__('body.description')" :value="old('description', $coupon->description)" />
            <x-common.select name="type" :label="__('body.type')" :value="old('type', $coupon->type?->value)" hide-placeholder required
                :options="['percent' => __('billing::billing.coupon_percent'), 'fixed' => __('billing::billing.coupon_fixed', ['currency' => \Modules\Billing\Support\BillingSettings::get('currency')])]" />
            <x-common.input name="value" type="number" step="0.01" min="0.01" :label="__('billing::billing.value')" :value="old('value', $coupon->value)" required />
            <x-common.select name="duration" :label="__('billing::billing.duration')" :value="old('duration', $coupon->duration?->value)" hide-placeholder required
                :options="collect(\Modules\Billing\Enums\CouponDuration::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])->all()" />
            <x-common.input name="duration_periods" type="number" min="1" :label="__('billing::billing.duration_periods')" :value="old('duration_periods', $coupon->duration_periods)" :hint="__('billing::billing.duration_periods_hint')" />
            <x-common.input name="max_redemptions" type="number" min="1" :label="__('billing::billing.max_redemptions')" :value="old('max_redemptions', $coupon->max_redemptions)" :hint="__('billing::billing.blank_unlimited')" />
            <x-common.input name="expires_at" type="date" :label="__('billing::billing.expires')" :value="old('expires_at', $coupon->expires_at?->toDateString())" />
        </div>

        <div>
            <p class="text-sm font-semibold text-ink mb-1">{{ __('billing::billing.coupon_packages') }}</p>
            <p class="text-sm text-muted mb-3">{{ __('billing::billing.coupon_packages_hint') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ($packages as $id => $name)
                    <x-common.checkbox name="package_ids[]" :value="$id" :label="$name" :checked="in_array((int) $id, $selected, true)" />
                @endforeach
            </div>
        </div>

        <input type="hidden" name="is_active" value="0">
        <x-common.checkbox name="is_active" :label="__('body.active')" :checked="(bool) old('is_active', $coupon->is_active)" />

        <div class="flex gap-3">
            <x-common.button href="{{ route('superadmin.billing.coupons.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
