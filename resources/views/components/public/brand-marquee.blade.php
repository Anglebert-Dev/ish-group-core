@props([
    'makes' => [],
])

@php
    $catalog = collect(\App\Libraries\BrandLibrary::all())
        ->filter(fn ($make) => filled(\App\Libraries\BrandLibrary::logoUrl($make)));

    $fleetMakes = collect($makes)
        ->filter()
        ->map(fn ($m) => trim((string) $m))
        ->filter(fn ($make) => filled(\App\Libraries\BrandLibrary::logoUrl($make)));

    $brands = $catalog
        ->concat($fleetMakes)
        ->unique(fn ($make) => \Illuminate\Support\Str::slug($make))
        ->values();

    $track = $brands->concat($brands);
@endphp

<section class="relative z-10 bg-white border-y border-line overflow-hidden" aria-label="{{ __('body.brand_marquee_label') }}">
    <div class="brand-marquee py-6 sm:py-8">
        <div class="brand-marquee-track">
            @foreach ($track as $make)
                <div class="brand-marquee-item" aria-hidden="{{ $loop->index >= $brands->count() ? 'true' : 'false' }}">
                    <x-public.brand-logo :make="$make" />
                </div>
            @endforeach
        </div>
    </div>
</section>
