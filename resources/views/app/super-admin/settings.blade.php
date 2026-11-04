@php
    $tabs = [
        'registration' => __('body.settings_tab_registration'),
        'security' => __('body.settings_tab_security'),
        'email' => __('body.settings_tab_email'),
        'system' => __('body.settings_tab_system'),
    ];
    $tab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'registration';
@endphp

<x-layouts.app :title="__('body.platform_settings')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.platform_settings')" :description="__('body.platform_settings_description')" />

    <div class="bg-card border border-line rounded-xl p-4 sm:p-6 grid grid-cols-1 md:grid-cols-[14rem_1fr] gap-6">
        {{-- Vertical tab list --}}
        <nav class="flex md:flex-col gap-1 overflow-x-auto">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('superadmin.settings.index', ['tab' => $key]) }}"
                   class="px-4 py-3 rounded-lg text-sm font-semibold text-center md:text-left whitespace-nowrap transition-colors {{ $tab === $key ? 'bg-brand text-on-brand' : 'text-body hover:bg-surface' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="min-w-0">
            @if ($tab === 'registration')
                <form action="{{ route('superadmin.settings.update') }}" method="POST" class="space-y-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="registration">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <x-common.checkbox name="allow_registration" :label="__('body.allow_registration')"
                            :description="__('body.allow_registration_hint')" :checked="$settings['allow_registration'] === '1'" />
                        <x-common.checkbox name="default_business_active_status" :label="__('body.default_business_active_status')"
                            :description="__('body.default_business_active_status_hint')" :checked="$settings['default_business_active_status'] === '1'" />
                        <x-common.checkbox name="allow_custom_css" :label="__('body.allow_custom_css')"
                            :description="__('body.allow_custom_css_hint')" :checked="$settings['allow_custom_css'] === '1'" />
                    </div>
                    <x-common.button type="submit">{{ __('body.update') }}</x-common.button>
                </form>
            @elseif ($tab === 'security')
                <form action="{{ route('superadmin.settings.update') }}" method="POST" class="space-y-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="security">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <x-common.input name="idle_timeout_minutes" type="number" min="0" max="1440" :label="__('body.idle_timeout_minutes')"
                            :hint="__('body.idle_timeout_minutes_hint')" :value="$settings['idle_timeout_minutes']" />
                        <x-common.checkbox name="business_manages_staff" :label="__('body.business_manages_staff')"
                            :description="__('body.business_manages_staff_hint')" :checked="$settings['business_manages_staff'] === '1'" />
                    </div>
                    <x-common.button type="submit">{{ __('body.update') }}</x-common.button>
                </form>
            @elseif ($tab === 'email')
                @include('app.super-admin.partials.mail-settings')
            @else
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($system as $label => $value)
                        <div class="rounded-xl bg-surface border border-line p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</dt>
                            <dd class="text-sm font-semibold text-ink mt-1 break-words">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>
</x-layouts.app>
