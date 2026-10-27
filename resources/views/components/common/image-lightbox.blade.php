@once
    <div
        data-image-lightbox
        class="fixed inset-0 z-[200] hidden items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-label="{{ __('body.images') }}"
    >
        <button
            type="button"
            data-image-lightbox-backdrop
            class="absolute inset-0 bg-ink/55 border-0 cursor-pointer"
            aria-label="{{ __('body.close') }}"
        ></button>

        <div class="relative z-10 w-1/2 max-w-[50vw] flex flex-col items-center gap-3 pointer-events-none">
            <button
                type="button"
                data-image-lightbox-close
                class="pointer-events-auto self-end inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-ink shadow-md border border-line hover:bg-white transition-colors"
                aria-label="{{ __('body.close') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img
                data-image-lightbox-img
                src=""
                alt=""
                class="pointer-events-auto w-full max-h-[80vh] object-contain rounded-xl shadow-2xl bg-white border border-line"
            >
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var root = document.querySelector('[data-image-lightbox]');
                if (!root || root.dataset.bound === '1') return;
                root.dataset.bound = '1';

                var img = root.querySelector('[data-image-lightbox-img]');
                var closeBtn = root.querySelector('[data-image-lightbox-close]');
                var backdrop = root.querySelector('[data-image-lightbox-backdrop]');

                function isOpen() {
                    return !root.classList.contains('hidden');
                }

                function openLightbox(src, alt) {
                    if (!src) return;
                    img.src = src;
                    img.alt = alt || '';
                    root.classList.remove('hidden');
                    root.classList.add('flex');
                    root.setAttribute('aria-hidden', 'false');
                    document.documentElement.classList.add('overflow-hidden');
                }

                function closeLightbox() {
                    root.classList.add('hidden');
                    root.classList.remove('flex');
                    root.setAttribute('aria-hidden', 'true');
                    img.removeAttribute('src');
                    img.alt = '';
                    document.documentElement.classList.remove('overflow-hidden');
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-lightbox-src]');
                    if (!trigger) return;
                    event.preventDefault();
                    openLightbox(
                        trigger.getAttribute('data-lightbox-src'),
                        trigger.getAttribute('data-lightbox-alt') || trigger.getAttribute('alt') || ''
                    );
                });

                if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
                if (backdrop) backdrop.addEventListener('click', closeLightbox);

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && isOpen()) {
                        closeLightbox();
                    }
                });
            })();
        </script>
    @endpush
@endonce
