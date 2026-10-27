@props(['paginator'])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = 2;
    $start = max(1, $current - $window);
    $end = min($last, $current + $window);

    $pageUrl = fn (int $page) => request()->fullUrlWithQuery(['page' => $page]);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-line px-4 sm:px-5 py-3.5']) }}>
    <p class="text-sm text-muted">Showing <span class="font-medium text-ink">{{ $paginator->firstItem() ?? 0 }}</span> to
        <span class="font-medium text-ink">{{ $paginator->lastItem() ?? 0 }}</span> of
        <span class="font-medium text-ink">{{ $paginator->total() }}</span> results
    </p>

    @if ($last > 1)
        <div class="flex items-center gap-1.5 self-end sm:self-auto">
            @if ($paginator->onFirstPage())
                <span class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-line cursor-not-allowed">&lsaquo;</span>
            @else
                <a href="{{ $pageUrl($current - 1) }}" class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-muted hover:bg-surface">&lsaquo;</a>
            @endif

            @if ($start > 1)
                <a href="{{ $pageUrl(1) }}" class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-sm text-body hover:bg-surface">1</a>
                @if ($start > 2)
                    <span class="h-8 w-8 flex items-center justify-center text-muted">&hellip;</span>
                @endif
            @endif

            @for ($page = $start; $page <= $end; $page++)
                @if ($page === $current)
                    <span class="h-8 w-8 flex items-center justify-center rounded-md bg-brand text-white text-sm font-medium">{{ $page }}</span>
                @else
                    <a href="{{ $pageUrl($page) }}" class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-sm text-body hover:bg-surface">{{ $page }}</a>
                @endif
            @endfor

            @if ($end < $last)
                @if ($end < $last - 1)
                    <span class="h-8 w-8 flex items-center justify-center text-muted">&hellip;</span>
                @endif
                <a href="{{ $pageUrl($last) }}" class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-sm text-body hover:bg-surface">{{ $last }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $pageUrl($current + 1) }}" class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-muted hover:bg-surface">&rsaquo;</a>
            @else
                <span class="h-8 w-8 flex items-center justify-center rounded-md border border-line text-line cursor-not-allowed">&rsaquo;</span>
            @endif
        </div>
    @endif
</div>
