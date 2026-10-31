@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_requirements') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.req_intro') }}</p>

    <ul class="divide-y divide-line text-sm mb-6">
        @foreach ($checks as $check)
            <li class="flex items-start justify-between gap-4 py-2.5">
                <div>
                    <div>{{ $check['label'] }}</div>
                    @if ($check['detail'] && (! $check['ok'] || $check['required']))
                        <div class="hint">{{ $check['detail'] }}</div>
                    @endif
                </div>
                <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1
                    {{ $check['ok'] ? 'bg-green-100 text-green-800' : ($check['required'] ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                    {{ $check['ok'] ? '✓' : ($check['required'] ? __('install.req_needed') : __('install.req_optional')) }}
                </span>
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('install.requirements.accept') }}" class="flex gap-3">
        @csrf
        <a href="{{ route('install.requirements') }}" class="btn btn-light">{{ __('install.recheck') }}</a>
        <button class="btn btn-primary disabled:opacity-50" @disabled(! $passes)>{{ __('install.continue') }}</button>
    </form>
@endsection
