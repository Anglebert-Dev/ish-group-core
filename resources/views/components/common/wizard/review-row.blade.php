@props(['label', 'for'])

<div class="flex items-start justify-between gap-4 py-2 border-b border-line last:border-0">
    <dt class="text-sm text-muted shrink-0">{{ $label }}</dt>
    <dd class="text-sm font-medium text-ink text-right" data-review-for="{{ $for }}">—</dd>
</div>
