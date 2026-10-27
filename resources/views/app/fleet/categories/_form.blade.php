@php
    $category = $category ?? null;
    $cancelHref = $cancelHref ?? route('fleet.categories.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="name" :label="__('body.category_name')" :value="old('name', $category?->name)" required />
        <x-common.input amount name="daily_price" :label="__('body.category_suggested_daily_price').' (RWF)'" :value="old('daily_price', $category?->daily_price)" required min="0" :hint="__('body.category_daily_price_hint')" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $category?->status?->value)" :options="\App\Enums\VehicleCategoryStatus::cases()" :hide-placeholder="true" />
        <x-common.textarea name="description" :label="__('body.description')" :value="old('description', $category?->description)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.image_step')">
    <x-common.file-input
        name="image"
        :label="__('body.image')"
        accept="image/*"
        :max-size-mb="4"
        :existing-url="$category?->imageUrl()"
    />
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.category_name')" for="name" />
        <x-common.wizard.review-row :label="__('body.daily_price')" for="daily_price" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
        <x-common.wizard.review-row :label="__('body.image')" for="image" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
