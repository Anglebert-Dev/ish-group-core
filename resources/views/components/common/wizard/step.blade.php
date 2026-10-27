@props([
    'step',
    'title' => null,
    'active' => false,
])

<div
    data-wizard-panel="{{ $step }}"
    class="form-wizard-step-panel bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5"
    @unless ($active) hidden @endunless
    {{ $attributes }}
>
    @if ($title)
        <p class="font-display font-bold text-ink">{{ $title }}</p>
    @endif

    {{ $slot }}
</div>
