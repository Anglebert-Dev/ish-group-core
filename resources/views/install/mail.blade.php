@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_mail') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.mail_intro') }}</p>

    @php($v = fn ($key) => old($key, $values[$key] ?? null))

    <form method="POST" action="{{ route('install.mail.save') }}" class="space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-[1fr_120px]">
            <div>
                <label class="label" for="host">{{ __('install.mail_host') }}</label>
                <input class="field" id="host" name="host" value="{{ $v('host') }}">
            </div>
            <div>
                <label class="label" for="port">{{ __('install.mail_port') }}</label>
                <input class="field" id="port" name="port" inputmode="numeric" value="{{ $v('port') }}">
            </div>
        </div>
        <div>
            <label class="label" for="scheme">{{ __('install.mail_scheme') }}</label>
            <select class="field" id="scheme" name="scheme">
                <option value="">{{ __('install.mail_scheme_auto') }}</option>
                <option value="smtps" @selected($v('scheme') === 'smtps')>{{ __('install.mail_scheme_smtps') }}</option>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="username">{{ __('install.mail_username') }}</label>
                <input class="field" id="username" name="username" value="{{ $v('username') }}" autocomplete="off">
            </div>
            <div>
                <label class="label" for="password">{{ __('install.mail_password') }}</label>
                <input class="field" id="password" name="password" type="password" autocomplete="new-password">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="from_address">{{ __('install.mail_from_address') }}</label>
                <input class="field" id="from_address" name="from_address" type="email" value="{{ $v('from_address') }}">
                <p class="hint">{{ __('install.mail_from_address_hint') }}</p>
            </div>
            <div>
                <label class="label" for="from_name">{{ __('install.mail_from_name') }}</label>
                <input class="field" id="from_name" name="from_name" value="{{ $v('from_name') }}">
            </div>
        </div>

        <div class="rounded-xl bg-gray-50 border border-line p-4 flex flex-col sm:flex-row gap-3 sm:items-end">
            <div class="flex-1">
                <label class="label" for="test_to">{{ __('install.mail_test_to') }}</label>
                <input class="field" id="test_to" name="test_to" type="email" value="{{ old('test_to') }}">
            </div>
            <button class="btn btn-light" name="action" value="test">{{ __('install.mail_send_test') }}</button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="btn btn-primary" name="action" value="save">{{ __('install.continue') }}</button>
            <button class="btn btn-light" name="action" value="skip" formnovalidate>{{ __('install.skip') }}</button>
        </div>
        <p class="hint">{{ __('install.mail_skip_hint') }}</p>
    </form>
@endsection
