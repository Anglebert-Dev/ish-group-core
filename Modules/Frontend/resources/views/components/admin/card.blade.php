@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5']) }}>
    <div>
        <h2 class="font-display font-bold text-ink">{{ $title }}</h2>
        @if ($description)
            <p class="text-sm text-muted mt-1">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
