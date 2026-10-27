@props(['title' => null, 'rows' => []])

{{-- Grey detail card: list of [label, value, icon?] rows. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface border border-line p-4 space-y-3']) }}>
    @if ($title)
        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $title }}</p>
    @endif
    @foreach ($rows as $row)
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-ink">
                @isset($row[2])
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row[2] }}"/></svg>
                @endisset
                {{ $row[0] }}
            </p>
            <p class="text-sm text-muted mt-0.5 break-words">{!! filled($row[1]) ? e($row[1]) : '—' !!}</p>
        </div>
    @endforeach
    {{ $slot }}
</div>
