@php
    $isNew = ! $package->exists;
    $limits = old('limits', $package->limits ?? []);
    $selectedModules = old('modules', $package->modules ?? []);
    $selectedBusinesses = array_map('intval', old('business_ids', $package->business_ids ?? []));
    $flag = fn ($name, $default = false) => (bool) old($name, $package->{$name} ?? $default);
@endphp
<x-layouts.app :title="$isNew ? __('billing::billing.add_package') : $package->name">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.packages')" :description="$isNew ? __('billing::billing.add_package') : __('billing::billing.edit_package', ['name' => $package->name])" />

    <form action="{{ $isNew ? route('superadmin.billing.packages.store') : route('superadmin.billing.packages.update', $package) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-8 space-y-8">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-5">
            <x-common.input name="name" :label="__('body.name')" :value="old('name', $package->name)" required />
            <x-common.input name="description" :label="__('billing::billing.package_description')" :value="old('description', $package->description)" />

            <x-common.input name="price" type="number" step="0.01" min="0" :label="__('billing::billing.price').' ('.\Modules\Billing\Support\BillingSettings::get('currency').')'" :value="old('price', $package->price ?? 0)" :hint="__('billing::billing.price_hint')" required />
            <x-common.input name="trial_days" type="number" min="0" :label="__('billing::billing.trial')" :value="old('trial_days', $package->trial_days ?? 0)" :hint="__('billing::billing.trial_hint')" />

            <x-common.select name="interval" :label="__('billing::billing.price_interval')" :value="old('interval', $package->interval?->value ?? 'month')" :options="\Modules\Billing\Enums\BillingInterval::options()" hide-placeholder required />
            <x-common.input name="interval_count" type="number" min="1" :label="__('billing::billing.interval_count')" :value="old('interval_count', $package->interval_count ?? 1)" :hint="__('billing::billing.interval_count_hint')" required />

            @foreach ($limitKeys as $key)
                <x-common.input :name="'limits['.$key.']'" type="number" min="0" :label="__('billing::billing.limit_label', ['item' => app(\App\Support\Entitlements\Quota::class)->label($key)])" :value="$limits[$key] ?? ''" :hint="__('billing::billing.zero_infinite')" />
            @endforeach
            <x-common.input name="sort_order" type="number" min="0" :label="__('billing::billing.sort_order')" :value="old('sort_order', $package->sort_order ?? 0)" />
        </div>

        <x-common.textarea name="features" :label="__('billing::billing.features')" :value="old('features', implode(PHP_EOL, $package->features ?? []))" :rows="4" :hint="__('billing::billing.features_hint')" />

        <div>
            <p class="text-sm font-semibold text-ink mb-1">{{ __('billing::billing.modules_included') }}</p>
            <p class="text-sm text-muted mb-3">{{ __('billing::billing.modules_included_hint') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @forelse ($modules as $module)
                    <x-common.checkbox name="modules[]" :value="$module['name']" :label="$module['name']" :checked="in_array($module['name'], $selectedModules, true)"
                        :description="$module['requires'] ? __('body.module_requires_list', ['modules' => implode(', ', $module['requires'])]) : null" />
                @empty
                    <p class="text-sm text-muted">{{ __('body.no_active_modules') }}</p>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach (['is_active' => [true, 'is_active'], 'is_popular' => [false, 'mark_popular'], 'is_private' => [false, 'private_only'], 'once_per_business' => [false, 'once_per_business']] as $field => [$default, $label])
                <input type="hidden" name="{{ $field }}" value="0">
                <x-common.checkbox :name="$field" :label="__('billing::billing.'.$label)" :description="__('billing::billing.'.$label.'_hint')" :checked="$flag($field, $default)" />
            @endforeach
        </div>

        <div>
            <p class="text-sm font-semibold text-ink mb-1">{{ __('billing::billing.only_for_businesses') }}</p>
            <p class="text-sm text-muted mb-3">{{ __('billing::billing.only_for_businesses_hint') }}</p>
            <select name="business_ids[]" multiple size="6" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                @foreach ($businesses as $id => $name)
                    <option value="{{ $id }}" @selected(in_array((int) $id, $selectedBusinesses, true))>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @unless ($isNew)
            <p class="text-xs text-muted">{{ __('billing::billing.package_edit_note') }}</p>
        @endunless

        <div class="flex justify-center gap-3">
            <x-common.button href="{{ route('superadmin.billing.packages.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
            @if ($package->exists && $subscriberCount > 0)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-2">
                    <p class="text-sm font-semibold text-ink">{{ __('billing::billing.apply_title', ['count' => $subscriberCount]) }}</p>
                    <p class="text-xs text-muted">{{ __('billing::billing.apply_hint') }}</p>
                    @foreach (['none', 'terms', 'all'] as $mode)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="apply_to_subscribers" value="{{ $mode }}" class="mt-1" @checked(old('apply_to_subscribers', 'none') === $mode)>
                            <span><b>{{ __('billing::billing.apply_'.$mode) }}</b><span class="block text-xs text-muted">{{ __('billing::billing.apply_'.$mode.'_hint') }}</span></span>
                        </label>
                    @endforeach
                </div>
            @endif
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
