@props([
    'pdf',
    'excel',
])

<div class="inline-flex items-center gap-2">
    <a
        href="{{ $pdf }}"
        class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-lg border border-line bg-white text-red-600 hover:border-red-500 hover:bg-red-50 transition-colors whitespace-nowrap"
        title="{{ __('body.export_pdf') }}"
        aria-label="{{ __('body.export_pdf') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
            <path d="M6 2h8l6 6v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm7 1.5V8h5.5L13 3.5zM8.2 13.2c.4-.9 1.1-1.4 2-1.4.7 0 1.2.3 1.5.8.3.4.4 1 .3 1.7-.1.9-.5 1.6-1.1 2.1-.5.4-1.2.6-2 .5l-.4 2.1H7.2l1-5.8zm1.2 3c.4 0 .7-.1.9-.4.3-.3.5-.8.5-1.3 0-.4-.1-.6-.3-.7-.1-.1-.3-.2-.5-.2-.4 0-.7.3-.9.8l-.4 1.8h.7zm4.1-4.4h1.4l.6 2.7.7-2.7h1.3l-1.4 5.8h-1.3l-.6-2.6-.7 2.6h-1.3l1.3-5.8z"/>
        </svg>
        {{ __('body.pdf') }}
    </a>
    <a
        href="{{ $excel }}"
        class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-lg border border-line bg-white text-green-700 hover:border-green-600 hover:bg-green-50 transition-colors whitespace-nowrap"
        title="{{ __('body.export_excel') }}"
        aria-label="{{ __('body.export_excel') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
            <path d="M6 2h8l6 6v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm7 1.5V8h5.5L13 3.5zM8 12.2l1.8 2.8L8 17.8h1.5l1.1-1.8 1.1 1.8H13l-1.8-2.8L13 12.2h-1.5L10.4 14 9.3 12.2H8zm6 .3h3.2v1h-2.1v1.1H17v1h-1.9v1.2h2.2v1H14v-5.3z"/>
        </svg>
        {{ __('body.excel') }}
    </a>
</div>
