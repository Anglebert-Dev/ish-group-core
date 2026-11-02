{{-- Print and PDF for any registered document: <x-print.buttons type="sales_invoice" :id="$invoice->getRouteKey()" /> --}}
@props(['type', 'id', 'size' => 'sm'])

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <a href="{{ route('print.show', [$type, $id]) }}" target="_blank" rel="noopener"
       class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-lg border border-line bg-white text-ink hover:border-brand hover:text-brand transition-colors whitespace-nowrap">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        {{ __('body.print') }}
    </a>
    <a href="{{ route('print.pdf', [$type, $id]) }}"
       class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-lg border border-line bg-white text-red-600 hover:border-red-500 hover:bg-red-50 transition-colors whitespace-nowrap">
        {{ __('body.pdf') }}
    </a>
</div>
