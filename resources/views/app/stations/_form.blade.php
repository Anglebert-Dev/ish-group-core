@php
    $station = $station ?? null;
    $cancelHref = $cancelHref ?? route('stations.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.identity')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="name" :label="__('body.station_name')" :value="old('name', $station?->name)" required />
        <x-common.input name="code" :label="__('body.code')" :value="old('code', $station?->code)" required placeholder="e.g. KGL-01" />
        <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $station?->phone)" />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $station?->email)" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $station?->status?->value)" :options="\App\Enums\StationStatus::cases()" :hide-placeholder="true" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.hours_address')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="city" :label="__('body.city')" :value="old('city', $station?->city)" />
        <x-common.input type="time" name="opening_time" :label="__('body.opening_time')" :value="old('opening_time', $station?->opening_time)" />
        <x-common.input type="time" name="closing_time" :label="__('body.closing_time')" :value="old('closing_time', $station?->closing_time)" />
        <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $station?->address)" :rows="2" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.station_name')" for="name" />
        <x-common.wizard.review-row :label="__('body.code')" for="code" />
        <x-common.wizard.review-row :label="__('body.phone')" for="phone" />
        <x-common.wizard.review-row :label="__('body.city')" for="city" />
        <x-common.wizard.review-row :label="__('body.opening_time')" for="opening_time" />
        <x-common.wizard.review-row :label="__('body.closing_time')" for="closing_time" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
