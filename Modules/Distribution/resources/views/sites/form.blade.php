@php
    $isNew = ! $site->exists;
    $selected = old('modules', []);
@endphp
<x-layouts.app :title="$isNew ? __('distribution::distribution.add_site') : $site->name">
    @include('distribution::partials.nav')

    <x-common.page-header :title="$isNew ? __('distribution::distribution.add_site') : __('distribution::distribution.edit_site')" :description="$isNew ? __('distribution::distribution.sites_description') : $site->name" />

    <form action="{{ $isNew ? route('superadmin.distribution.sites.store') : route('superadmin.distribution.sites.update', $site) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-8 space-y-8">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-5">
            <x-common.input name="name" :label="__('distribution::distribution.site_name')" :value="old('name', $site->name)" required />
            <x-common.input name="customer_name" :label="__('distribution::distribution.customer_name')" :value="old('customer_name', $site->customer_name)" />
            <x-common.input name="contact_email" type="email" :label="__('distribution::distribution.contact_email')" :value="old('contact_email', $site->contact_email)" />
            <x-common.input name="app_url" type="url" :label="__('distribution::distribution.app_url')" :value="old('app_url', $site->app_url)" :hint="__('distribution::distribution.app_url_hint')" />
            <x-common.select name="update_mode" :label="__('distribution::distribution.update_mode')" :value="old('update_mode', $site->update_mode?->value)" hide-placeholder required
                :options="collect(\Modules\Distribution\Enums\UpdateMode::cases())->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()])->all()"
                :hint="__('distribution::distribution.update_mode_hint')" />
            <x-common.select name="status" :label="__('distribution::distribution.status')" :value="old('status', $site->status?->value)" hide-placeholder required
                :options="collect(\Modules\Distribution\Enums\SiteStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()"
                :hint="__('distribution::distribution.status_hint')" />
        </div>

        <input type="hidden" name="is_test" value="0">
        <x-common.checkbox name="is_test" value="1" :label="__('distribution::distribution.is_test')" :description="__('distribution::distribution.is_test_hint')" :checked="(bool) old('is_test', $site->is_test)" />

        <x-common.textarea name="notes" :label="__('distribution::distribution.notes')" :value="old('notes', $site->notes)" :rows="3" />

        @if ($isNew)
            <div>
                <p class="text-sm font-semibold text-ink mb-1">{{ __('distribution::distribution.modules') }}</p>
                <p class="text-sm text-muted mb-3">{{ __('distribution::distribution.modules_hint') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($modules as $module)
                        @php $requires = app(\App\Services\Module\ModuleManager::class)->requires($module); @endphp
                        <x-common.checkbox name="modules[]" :value="$module" :label="$module" :checked="in_array($module, $selected, true)"
                            :description="$requires ? __('body.module_requires_list', ['modules' => implode(', ', $requires)]) : null" />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
            <x-common.button :href="$isNew ? route('superadmin.distribution.sites.index') : route('superadmin.distribution.sites.show', $site)" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
