@props(['menu' => false])

{{-- Language switcher (App\Support\Locale\Locales). In the user menu it always shows; elsewhere only when there is a choice. --}}
@php
    $locales = \App\Support\Locale\Locales::available();
    $current = app()->getLocale();
@endphp

@if ($menu)
    <div class="px-4 pt-2 pb-1 border-t border-line">
        <p class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('body.language') }}</p>
    </div>
    @foreach ($locales as $code => $name)
        <form action="{{ route('locale.update') }}" method="POST">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button type="submit" class="flex items-center justify-between gap-3 px-4 py-2 text-sm text-ink hover:bg-surface transition-colors w-full text-left" @if ($code === $current) aria-current="true" @endif>
                <span lang="{{ $code }}">{{ $name }}</span>
                @if ($code === $current)
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @endif
            </button>
        </form>
    @endforeach
@elseif (count($locales) > 1)
    <form action="{{ route('locale.update') }}" method="POST" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm text-muted']) }}>
        @csrf
        <label for="language-switcher" class="sr-only">{{ __('body.language') }}</label>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
        <select id="language-switcher" name="locale" onchange="this.form.submit()" class="bg-transparent border-0 p-0 pr-6 text-sm text-muted focus:ring-0 cursor-pointer">
            @foreach ($locales as $code => $name)
                <option value="{{ $code }}" @selected($code === $current)>{{ $name }}</option>
            @endforeach
        </select>
    </form>
@endif
