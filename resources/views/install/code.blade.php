@extends('install.layout')

@section('content')
    <h2 class="text-lg font-semibold mb-1">{{ __('install.step_code') }}</h2>
    <p class="text-sm text-muted mb-5">{{ __('install.code_intro') }}</p>

    <form method="POST" action="{{ route('install.code.check') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="code">{{ __('install.code_label') }}</label>
            <input class="field uppercase tracking-widest" id="code" name="code" required autofocus autocomplete="off">
        </div>
        <button class="btn btn-primary">{{ __('install.continue') }}</button>
    </form>
@endsection
