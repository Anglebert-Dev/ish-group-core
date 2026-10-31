@php
    $sections = [
        'server' => ['hosting_type', 'php_version', 'php_path', 'install_path', 'web_root_is_public', 'app_url', 'timezone', 'cron_only'],
        'database' => ['db_driver', 'db_host', 'db_port', 'db_database', 'db_username'],
        'mail' => ['mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address'],
        'account' => ['business_name', 'admin_name', 'admin_email'],
    ];
    $choices = [
        'hosting_type' => ['shared' => 'Shared (cPanel/Plesk)', 'vps' => 'VPS / own server', 'docker' => 'Docker'],
        'db_driver' => ['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL'],
        'mail_encryption' => ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => __('distribution::distribution.mail_encryption_none')],
        'web_root_is_public' => ['1' => __('distribution::distribution.yes'), '0' => __('distribution::distribution.no')],
        'cron_only' => ['1' => __('distribution::distribution.yes'), '0' => __('distribution::distribution.no')],
    ];
    $value = function (string $key) use ($details) {
        $current = old($key, $details[$key] ?? null);
        return is_bool($current) ? ($current ? '1' : '0') : $current;
    };
@endphp
<x-layouts.app :title="__('distribution::distribution.server_details')">
    @include('distribution::partials.nav')

    <x-common.page-header :title="__('distribution::distribution.server_details')" :description="$site->name" />

    <form action="{{ route('superadmin.distribution.sites.server.update', $site) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-8 space-y-8">
        @csrf
        @method('PUT')
        <p class="text-sm text-muted">{{ __('distribution::distribution.server_form_intro') }}</p>

        @foreach ($sections as $section => $keys)
            <div>
                <p class="font-semibold text-ink mb-3">{{ __('distribution::distribution.server_section_'.$section) }}</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-5">
                    @foreach ($keys as $key)
                        @if (isset($choices[$key]))
                            <x-common.select :name="$key" :label="__('distribution::distribution.server_'.$key)" :value="$value($key)" :options="$choices[$key]"
                                :hint="__('distribution::distribution.server_'.$key.'_help')" />
                        @else
                            <x-common.input :name="$key" :label="__('distribution::distribution.server_'.$key)" :value="$value($key)"
                                :placeholder="$fields[$key][1]" :hint="__('distribution::distribution.server_'.$key.'_help')" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
            <x-common.button :href="route('superadmin.distribution.sites.show', $site)" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
