@props(['vehicle'])

@php
    $transmissionShort = $vehicle->transmission === \App\Enums\TransmissionType::Automatic ? 'Auto' : 'Manual';
    $title = trim(($vehicle->make ?? '').' '.($vehicle->model ?? '')) ?: $vehicle->name();
    $location = $vehicle->station?->city
        ?: $vehicle->station?->name
        ?: __('body.kigali');
    $chips = array_filter([
        $vehicle->year ? (string) $vehicle->year : null,
        $vehicle->seats ? __('body.seats_chip', ['count' => $vehicle->seats]) : null,
        $transmissionShort,
        $vehicle->fuel_type?->label(),
    ]);
@endphp

<article
    class="vehicle-card bg-white rounded-2xl border border-line overflow-hidden shadow-sm hover:shadow-md hover:border-brand/30 transition-all duration-200 h-full flex flex-col"
    data-vehicle-type="{{ $vehicle->vehicle_type?->value }}"
    data-vehicle-category="{{ $vehicle->vehicle_category_id }}"
    data-vehicle-make="{{ \Illuminate\Support\Str::slug($vehicle->make ?? '') }}"
>
    <div class="relative aspect-[16/11] bg-surface overflow-hidden">
        @if ($vehicle->coverImageUrl())
            <img src="{{ $vehicle->coverImageUrl() }}" alt="{{ $title }}" class="h-full w-full object-cover">
        @else
            <div class="h-full w-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"/></svg>
            </div>
        @endif
    </div>

    <div class="p-4 flex flex-col flex-1">
        <h3 class="font-display font-bold text-ink text-[1.05rem] leading-tight tracking-tight uppercase">{{ $title }}</h3>

        <p class="mt-1.5 flex items-center gap-1.5 text-sm text-muted">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            {{ $location }}
        </p>

        @if ($chips)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($chips as $chip)
                    <span class="inline-flex items-center rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] font-medium text-body">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <div class="mt-auto pt-4 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs text-muted truncate">{{ $vehicle->category?->name ?? $vehicle->vehicle_type?->label() ?? '—' }}</p>
                <p class="font-display font-bold text-brand text-lg leading-tight mt-0.5">
                    {{ number_format($vehicle->dailyPrice() ?? 0, 0) }}
                    <span class="text-sm font-semibold text-brand/80">RWF</span>
                    <span class="text-xs font-medium text-muted">/d</span>
                </p>
            </div>
            <a href="{{ route('vehicles.show', $vehicle) }}"
               class="inline-flex items-center justify-center shrink-0 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold px-5 py-2.5 transition-colors">
                {{ __('body.book') }}
            </a>
        </div>
    </div>
</article>
