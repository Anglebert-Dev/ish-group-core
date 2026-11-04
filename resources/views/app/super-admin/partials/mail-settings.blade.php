{{-- Platform settings → Email: the SMTP account this system sends from. --}}
<div class="space-y-5">
    <div>
        <p class="text-sm text-body">{{ __('body.mail_settings_intro') }}</p>
        <p class="text-xs text-muted mt-1">{{ __('body.mail_source_'.$mail['source']) }}</p>
    </div>

    @if ($mail['unreadable'])
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-ink">{{ __('body.mail_unreadable') }}</div>
    @endif

    <div class="rounded-xl bg-surface border border-line p-4 text-sm">
        <p class="font-semibold text-ink">{{ __('body.mail_cpanel_title') }}</p>
        <p class="text-muted mt-1">{{ __('body.mail_cpanel_steps') }}</p>
    </div>

    <form action="{{ route('superadmin.settings.mail.update') }}" method="POST" class="space-y-5">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-common.input name="host" :label="__('install.mail_host')" :value="old('host', $mail['host'])" placeholder="mail.yourdomain.com" required />
            <div class="grid grid-cols-[6rem_1fr] gap-4">
                <x-common.input name="port" type="number" min="1" max="65535" :label="__('install.mail_port')" :value="old('port', $mail['port'])" required />
                <x-common.select name="scheme" :label="__('install.mail_scheme')" hidePlaceholder :value="old('scheme', $mail['scheme'] === 'smtps' ? 'smtps' : '')"
                    :options="['' => __('install.mail_scheme_auto'), 'smtps' => __('install.mail_scheme_smtps')]" />
            </div>
            <x-common.input name="username" :label="__('install.mail_username')" :value="old('username', $mail['username'])" autocomplete="off" />
            <x-common.input name="password" type="password" :label="__('install.mail_password')" autocomplete="new-password" :hint="$mail['has_password'] ? __('body.mail_password_keep') : null" />
            <x-common.input name="from_address" type="email" :label="__('install.mail_from_address')" :value="old('from_address', $mail['from_address'])" :hint="__('install.mail_from_address_hint')" required />
            <x-common.input name="from_name" :label="__('install.mail_from_name')" :value="old('from_name', $mail['from_name'])" />
        </div>

        <div class="rounded-xl border border-line p-4 space-y-4">
            <x-common.input name="test_to" type="email" :label="__('install.mail_test_to')" :value="old('test_to', auth('staff')->user()?->email)" :hint="__('body.mail_test_hint')" />
            <div class="flex flex-wrap gap-3">
                <x-common.button type="submit" name="action" value="test">{{ __('body.mail_test_save') }}</x-common.button>
                <x-common.button type="submit" name="action" value="save" variant="secondary">{{ __('body.mail_save_only') }}</x-common.button>
            </div>
        </div>
    </form>

    @if ($mail['source'] === 'saved')
        <form action="{{ route('superadmin.settings.mail.reset') }}" method="POST" class="border-t border-line pt-4 flex flex-wrap items-center justify-between gap-3">
            @csrf @method('DELETE')
            <p class="text-xs text-muted">{{ __('body.mail_reset_hint') }}</p>
            <x-common.button type="submit" variant="secondary" size="sm">{{ __('body.mail_reset') }}</x-common.button>
        </form>
    @endif
</div>
