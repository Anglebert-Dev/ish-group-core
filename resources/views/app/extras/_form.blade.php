@php
    $extra = $extra ?? null;
    $cancelHref = $cancelHref ?? route('extras.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="name" :label="__('body.extra_name')" :value="old('name', $extra?->name)" required class="sm:col-span-2" />
        <x-common.input amount name="price" :label="__('body.price')" :value="old('price', $extra?->price)" required min="0" :hint="__('body.typed_extra_price_hint')" />
        <x-common.select name="type" :label="__('body.extra_type')" :value="old('type', $extra?->type?->value ?? \App\Enums\ExtraType::Optional->value)" :options="\App\Enums\ExtraType::cases()" :hide-placeholder="true" :hint="__('body.typed_extra_type_hint')" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $extra?->status?->value)" :options="\App\Enums\ExtraStatus::cases()" :hide-placeholder="true" />
        <x-common.textarea name="description" :label="__('body.description')" :value="old('description', $extra?->description)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.extra_name')" for="name" />
        <x-common.wizard.review-row :label="__('body.price')" for="price" />
        <x-common.wizard.review-row :label="__('body.extra_type')" for="type" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
        <x-common.wizard.review-row :label="__('body.description')" for="description" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
