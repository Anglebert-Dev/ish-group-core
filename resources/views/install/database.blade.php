@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_database') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.db_intro') }}</p>

    @php
        $v = fn ($key, $default = null) => old($key, $values[$key] ?? $default);
        $driver = $v('driver', $drivers[0] ?? 'mysql');
    @endphp

    <form method="POST" action="{{ route('install.database.save') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="driver">{{ __('install.db_driver') }}</label>
            <select class="field" id="driver" name="driver"
                    onchange="var p=document.getElementById('port'); if(p.value==='3306'||p.value==='5432'||!p.value){p.value=this.value==='pgsql'?'5432':'3306';}">
                @foreach (['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL'] as $key => $label)
                    <option value="{{ $key }}" @selected($driver === $key) @disabled(! in_array($key, $drivers, true))>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-[1fr_120px]">
            <div>
                <label class="label" for="host">{{ __('install.db_host') }}</label>
                <input class="field" id="host" name="host" value="{{ $v('host', '127.0.0.1') }}" required>
            </div>
            <div>
                <label class="label" for="port">{{ __('install.db_port') }}</label>
                <input class="field" id="port" name="port" inputmode="numeric" value="{{ $v('port', $driver === 'pgsql' ? 5432 : 3306) }}" required>
            </div>
        </div>
        <div>
            <label class="label" for="database">{{ __('install.db_database') }}</label>
            <input class="field" id="database" name="database" value="{{ $v('database') }}" required>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="username">{{ __('install.db_username') }}</label>
                <input class="field" id="username" name="username" value="{{ $v('username') }}" required autocomplete="off">
            </div>
            <div>
                <label class="label" for="password">{{ __('install.db_password') }}</label>
                <input class="field" id="password" name="password" type="password" autocomplete="new-password">
            </div>
        </div>
        <button class="btn btn-primary">{{ __('install.continue') }}</button>
    </form>
@endsection
