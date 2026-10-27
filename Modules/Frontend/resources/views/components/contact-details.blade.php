@props(['contact'])

@php
    $rows = array_filter([
        'address' => $contact['address'] ?? '',
        'phone' => $contact['phone'] ?? '',
        'email' => $contact['email'] ?? '',
    ], fn ($value) => filled($value));

    $icons = [
        'address' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'phone' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
        'email' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    ];
    $labels = [
        'address' => __('body.contact_location_label'),
        'phone' => __('body.contact_phone_label'),
        'email' => __('body.contact_email_label'),
    ];
    $links = [
        'phone' => fn ($v) => 'tel:'.preg_replace('/[^\d+]/', '', $v),
        'email' => fn ($v) => 'mailto:'.$v,
    ];
@endphp

@if ($rows === [])
    <p class="text-sm text-muted">{{ __('body.contact_details_missing') }}</p>
@else
    <ul {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-3 gap-3']) }}>
        @foreach ($rows as $type => $value)
            <li>
                <{{ isset($links[$type]) ? 'a' : 'div' }} @isset($links[$type]) href="{{ $links[$type]($value) }}" @endisset
                    class="flex items-start gap-3.5 rounded-2xl bg-white border border-line px-4 py-3.5 h-full {{ isset($links[$type]) ? 'hover:border-brand/40 transition-colors' : '' }}">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$type] }}"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted">{{ $labels[$type] }}</span>
                        <span class="block mt-0.5 text-ink font-semibold break-words">{{ $value }}</span>
                    </span>
                </{{ isset($links[$type]) ? 'a' : 'div' }}>
            </li>
        @endforeach
    </ul>
@endif
