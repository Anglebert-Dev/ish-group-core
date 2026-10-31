@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.finish_title') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.finish_intro') }}</p>

    <div class="space-y-5 text-sm">
        <div>
            <p class="mb-2">{{ __('install.finish_cron') }}</p>
            <pre class="rounded-xl bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs">* * * * * cd {{ $basePath }} && php artisan schedule:run >> /dev/null 2>&1</pre>
        </div>
        <div>
            @if ($queueViaScheduler)
                <p>{{ __('install.finish_queue_cron') }}</p>
            @else
                <p class="mb-2">{{ __('install.finish_queue') }}</p>
                <pre class="rounded-xl bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs">cd {{ $basePath }} && php artisan queue:work --tries=3</pre>
            @endif
        </div>
    </div>

    <a href="{{ $loginUrl }}" class="btn btn-primary mt-6">{{ __('install.finish_login') }}</a>
@endsection
