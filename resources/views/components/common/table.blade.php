{{-- Generic table shell. Prefer composing <table> markup directly in views for
     full control over columns; this wrapper just standardises the outer card,
     border and horizontal scroll behaviour used across the dashboard. --}}
@props(['headers' => []])

<div {{ $attributes->merge(['class' => 'bg-white border border-line rounded-xl overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @if (count($headers))
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        @foreach ($headers as $header)
                            <th class="px-5 py-3 font-semibold">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-line">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
