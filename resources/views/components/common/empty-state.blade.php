@props(['title' => 'Nothing here yet', 'description' => null, 'icon' => null])

<div class="flex flex-col items-center justify-center text-center py-16 px-6 border border-dashed border-line rounded-xl bg-surface">
    <div class="h-12 w-12 rounded-full bg-white border border-line flex items-center justify-center mb-4 text-muted">
        {!! $icon ?? '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' !!}
    </div>
    <p class="font-semibold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="text-sm text-muted mt-1 max-w-sm">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
