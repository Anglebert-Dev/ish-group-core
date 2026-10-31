@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_admin') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.admin_intro') }}</p>

    @php($v = fn ($key) => old($key, $values[$key] ?? null))

    <form method="POST" action="{{ route('install.admin.save') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="business">{{ __('install.admin_business') }}</label>
            <input class="field" id="business" name="business" value="{{ $v('business') }}" required>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="name">{{ __('install.admin_name') }}</label>
                <input class="field" id="name" name="name" value="{{ $v('name') }}" required autocomplete="name">
            </div>
            <div>
                <label class="label" for="email">{{ __('install.admin_email') }}</label>
                <input class="field" id="email" name="email" type="email" value="{{ $v('email') }}" required autocomplete="username">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="password">{{ __('install.admin_password') }}</label>
                <input class="field" id="password" name="password" type="password" required autocomplete="new-password">
                <p class="hint">{{ __('install.admin_password_hint') }}</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">{{ __('install.admin_password_confirmation') }}</label>
                <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
        </div>
        <button class="btn btn-primary">{{ __('install.continue') }}</button>
    </form>
@endsection
