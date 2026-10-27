@props(['title', 'description' => null])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-display font-bold text-ink break-words">{{ $title }}</h1>
        @if ($description)
            <p class="text-muted text-sm mt-1">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 w-full sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none">
            {{ $actions }}
        </div>
    @endisset
</div>
