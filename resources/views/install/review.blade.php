@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_review') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.review_intro') }}</p>

    <dl class="grid grid-cols-[140px_1fr] gap-y-2 text-sm mb-6">
        <dt class="text-muted">{{ __('install.step_database') }}</dt>
        <dd>{{ $data['database']['driver'] === 'pgsql' ? 'PostgreSQL' : 'MySQL' }} · {{ $data['database']['username'].'@'.$data['database']['host'].':'.$data['database']['port'].'/'.$data['database']['database'] }}</dd>
        <dt class="text-muted">{{ __('install.step_app') }}</dt>
        <dd>{{ $data['app']['name'] }} · {{ $data['app']['url'] }} · {{ $data['app']['timezone'] }}</dd>
        <dt class="text-muted">{{ __('install.step_mail') }}</dt>
        <dd>{{ $data['mail'] ? $data['mail']['host'].' · '.$data['mail']['from_address'] : __('install.skip') }}</dd>
        <dt class="text-muted">{{ __('install.step_admin') }}</dt>
        <dd>{{ $data['admin']['name'] }} · {{ $data['admin']['email'] }} · {{ $data['admin']['business'] }}</dd>
        <dt class="text-muted">{{ __('install.review_modules') }}</dt>
        <dd>{{ $modules ? implode(', ', $modules) : __('install.review_no_modules') }}</dd>
    </dl>

    <form method="POST" action="{{ route('install.run') }}"
          onsubmit="var b=this.querySelector('button'); b.disabled=true; b.textContent='…';">
        @csrf
        <button class="btn btn-primary">{{ session('error') ? __('install.retry') : __('install.install_now') }}</button>
    </form>
@endsection
