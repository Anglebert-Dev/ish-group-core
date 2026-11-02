@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.finish_title') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.finish_intro') }}</p>

    <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm mb-5">{{ __('install.finish_warning') }}</div>

    <div class="space-y-5 text-sm">
        @if ($devServer)
            <div>
                <p class="mb-2">{{ __('install.finish_dev') }}</p>
                <pre class="rounded-xl bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs">cd {{ $basePath }}
php artisan schedule:work{{ $queueViaScheduler ? '' : "\nphp artisan queue:work --tries=3" }}</pre>
                <p class="text-muted mt-2">{{ __('install.finish_dev_hint') }}</p>
            </div>
        @endif
        <div>
            <p class="mb-2">{{ __('install.finish_cron') }}</p>
            <pre class="rounded-xl bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs">{{ $cronLine }}</pre>
        </div>
        <div>
            @if ($queueViaScheduler)
                <p>{{ $devServer ? __('install.finish_queue_dev') : __('install.finish_queue_cron') }}</p>
            @else
                <p class="mb-2">{{ __('install.finish_queue') }}</p>
                <pre class="rounded-xl bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs">cd {{ $basePath }} && php artisan queue:work --tries=3</pre>
            @endif
        </div>
    </div>

    <a href="{{ $loginUrl }}" class="btn btn-primary mt-6">{{ __('install.finish_login') }}</a>
@endsection
