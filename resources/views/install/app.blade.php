@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_app') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.app_intro') }}</p>

    @php($v = fn ($key) => old($key, $values[$key] ?? null))

    <form method="POST" action="{{ route('install.app.save') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="name">{{ __('install.app_name') }}</label>
            <input class="field" id="name" name="name" value="{{ $v('name') }}" required>
        </div>
        <div>
            <label class="label" for="url">{{ __('install.app_url') }}</label>
            <input class="field" id="url" name="url" type="url" value="{{ $v('url') }}" required>
            <p class="hint">{{ __('install.app_url_hint') }}</p>
        </div>
        <div>
            <label class="label" for="timezone">{{ __('install.app_timezone') }}</label>
            <select class="field" id="timezone" name="timezone">
                @foreach ($timezones as $timezone)
                    <option value="{{ $timezone }}" @selected($v('timezone') === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="queue_via_scheduler" value="1" class="mt-1" @checked($v('queue_via_scheduler'))>
            <span>
                <span class="font-semibold">{{ __('install.app_queue_via_scheduler') }}</span>
                <span class="hint block">{{ __('install.app_queue_via_scheduler_hint') }}</span>
            </span>
        </label>
        <button class="btn btn-primary">{{ __('install.continue') }}</button>
    </form>
@endsection
