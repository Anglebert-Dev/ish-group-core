<x-layouts.app title="{{ __('body.my_profile') }}">
    <x-common.page-header :title="__('body.my_profile')" :description="__('body.profile_description')" />

    <div class="w-full space-y-6">

        {{-- ACCOUNT DETAILS --}}
        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full">
            <h2 class="font-display font-bold text-ink">{{ __('body.account_details') }}</h2>
            <p class="text-sm text-muted mt-1 mb-5">{{ __('body.account_details_description') }}</p>

            @if ($user->last_login_at)
                <p class="text-sm text-muted mb-4">
                    {{ __('body.last_login') }}:
                    <span class="text-body">@kigali($user->last_login_at)</span>
                    @if ($user->last_login_ip)
                        <span class="text-muted">({{ $user->last_login_ip }})</span>
                    @endif
                </p>
            @endif

            <form action="{{ route('staff.profile.update') }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.input name="name" :label="__('body.full_name')" :value="old('name', $user->name)" required autocomplete="name" />
                    <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $user->email)" required autocomplete="username" />
                </div>
                <div class="flex justify-end pt-2 border-t border-line">
                    <x-common.button type="submit">{{ __('body.save_changes') }}</x-common.button>
                </div>
            </form>
        </div>

        {{-- CHANGE PASSWORD --}}
        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full">
            <h2 class="font-display font-bold text-ink">{{ __('body.change_password') }}</h2>
            <p class="text-sm text-muted mt-1 mb-5">{{ __('body.change_password_description') }}</p>

            <form action="{{ route('staff.profile.password') }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')
                <x-common.input type="password" name="current_password" :label="__('body.current_password')" required autocomplete="current-password" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.input type="password" name="password" :label="__('body.new_password')" required autocomplete="new-password" strength />
                    <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" confirm-for="field-password" />
                </div>
                <div class="flex justify-end pt-2 border-t border-line">
                    <x-common.button type="submit">{{ __('body.update_password') }}</x-common.button>
                </div>
            </form>
        </div>

        {{-- MFA --}}
        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full space-y-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('body.mfa_setup_title') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('body.mfa_secret_hint') }}</p>
            </div>

            @if ($mfaEnabled)
                <p class="text-sm text-success font-medium">{{ __('body.mfa_enabled') }}</p>
                <p class="text-sm text-muted">{{ __('body.mfa_recovery_remaining', ['count' => $mfaRecoveryRemaining ?? 0]) }}</p>
                <div class="flex flex-wrap gap-3">
                    <form action="{{ route('staff.profile.mfa.recovery.regenerate') }}" method="POST">
                        @csrf
                        <x-common.button type="submit" variant="secondary"
                            data-confirm
                            data-confirm-title="{{ __('body.mfa_recovery_regenerate') }}?"
                            data-confirm-message="{{ __('body.mfa_recovery_regenerate_confirm_message') }}"
                            data-confirm-variant="danger"
                            data-confirm-text="{{ __('body.mfa_recovery_regenerate') }}"
                        >{{ __('body.mfa_recovery_regenerate') }}</x-common.button>
                    </form>
                    <form action="{{ route('staff.profile.mfa.disable') }}" method="POST">
                        @csrf
                        <x-common.button type="submit" variant="danger">{{ __('body.mfa_disable') }}</x-common.button>
                    </form>
                </div>
            @elseif ($mfaSetup)
                <div class="flex flex-col sm:flex-row gap-5 items-start">
                    <img src="{{ $mfaQrCode }}" alt="{{ __('body.mfa_qr_alt') }}" class="h-44 w-44 rounded-lg border border-line p-2 bg-white shrink-0">
                    <div class="space-y-2 min-w-0">
                        <p class="text-sm text-muted">{{ __('body.mfa_secret_hint') }}</p>
                        <p class="text-sm text-body break-all font-mono bg-surface border border-line rounded-lg p-3">{{ $mfaSetup['secret'] }}</p>
                    </div>
                </div>
                <form action="{{ route('staff.profile.mfa.confirm') }}" method="POST" class="space-y-4">
                    @csrf
                    <x-common.input name="code" :label="__('body.mfa_code')" required autocomplete="one-time-code" inputmode="numeric" />
                    <div class="flex gap-3">
                        <x-common.button type="submit">{{ __('body.mfa_confirm') }}</x-common.button>
                        <x-common.button type="submit" formaction="{{ route('staff.profile.mfa.cancel') }}" formnovalidate variant="secondary">{{ __('body.mfa_cancel') }}</x-common.button>
                    </div>
                </form>
            @else
                <form action="{{ route('staff.profile.mfa.setup') }}" method="POST">
                    @csrf
                    <x-common.button type="submit">{{ __('body.mfa_setup_title') }}</x-common.button>
                </form>
            @endif
        </div>

        {{-- RECOVERY CODES (shown once, right after they're generated) --}}
        @if ($mfaRecoveryCodes)
            <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full space-y-4">
                <div>
                    <h2 class="font-display font-bold text-ink">{{ __('body.mfa_recovery_codes_title') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('body.mfa_recovery_codes_hint') }}</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-sm">
                    @foreach ($mfaRecoveryCodes as $recoveryCode)
                        <div class="bg-surface border border-line rounded-lg px-3 py-2 text-center text-ink">{{ $recoveryCode }}</div>
                    @endforeach
                </div>
                <a href="data:text/plain;charset=utf-8,{{ rawurlencode(implode(PHP_EOL, $mfaRecoveryCodes)) }}"
                   download="afriforge-recovery-codes.txt"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:text-brand-dark">
                    {{ __('body.mfa_recovery_codes_download') }}
                </a>
            </div>
        @endif

        {{-- LOGOUT --}}
        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('body.log_out') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('body.log_out_of_account') }}</p>
            </div>
            <form action="{{ route('staff.logout') }}" method="POST">
                @csrf
                <x-common.button type="submit" variant="danger">{{ __('body.log_out') }}</x-common.button>
            </form>
        </div>

    </div>
</x-layouts.app>
