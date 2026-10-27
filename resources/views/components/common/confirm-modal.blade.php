<div id="confirm-modal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-ink/50" data-confirm-cancel></div>
    <div class="relative min-h-full flex items-end sm:items-center justify-center p-4 sm:p-6">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl max-w-sm w-full p-5 sm:p-6">
            <h3 id="confirm-modal-title" class="font-display font-bold text-lg text-ink"></h3>
            <p id="confirm-modal-message" class="text-sm text-muted mt-2 leading-relaxed"></p>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-2 sm:gap-3 mt-6">
                <button type="button" data-confirm-cancel class="h-11 sm:h-10 px-4 rounded-lg border border-line text-sm font-medium text-body hover:border-brand">
                    Cancel
                </button>
                <button type="button" id="confirm-modal-confirm" class="h-11 sm:h-10 px-4 rounded-lg text-sm font-semibold text-white bg-danger hover:opacity-90">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('confirm-modal');
    var titleEl = document.getElementById('confirm-modal-title');
    var messageEl = document.getElementById('confirm-modal-message');
    var confirmBtn = document.getElementById('confirm-modal-confirm');
    var pending = null;

    var variantClasses = {
        danger: 'bg-danger hover:opacity-90',
        brand: 'bg-brand hover:bg-brand-dark',
    };

    window.openConfirm = function (opts) {
        opts = opts || {};
        titleEl.textContent = opts.title || 'Are you sure?';
        messageEl.textContent = opts.message || 'This action cannot be undone.';
        confirmBtn.textContent = opts.confirmText || 'Confirm';
        confirmBtn.className = 'h-11 sm:h-10 px-4 rounded-lg text-sm font-semibold text-white ' + (variantClasses[opts.variant] || variantClasses.danger);
        pending = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;
        modal.classList.remove('hidden');
    };

    function closeModal() {
        modal.classList.add('hidden');
        pending = null;
    }

    confirmBtn.addEventListener('click', function () {
        var callback = pending;
        closeModal();
        if (callback) callback();
    });

    modal.querySelectorAll('[data-confirm-cancel]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    // Declarative usage: add data-confirm to any button/link.
    // Optional: data-confirm-title, data-confirm-message, data-confirm-text,
    // data-confirm-variant ("danger" | "brand"), data-confirm-toast,
    // data-confirm-toast-type, data-confirm-href.
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-confirm]');
        if (!trigger) return;
        e.preventDefault();

        window.openConfirm({
            title: trigger.dataset.confirmTitle,
            message: trigger.dataset.confirmMessage,
            confirmText: trigger.dataset.confirmText,
            variant: trigger.dataset.confirmVariant,
            onConfirm: function () {
                if (trigger.dataset.confirmToast && typeof window.toast === 'function') {
                    window.toast(trigger.dataset.confirmToast, trigger.dataset.confirmToastType || 'success');
                }

                var href = trigger.dataset.confirmHref || (trigger.tagName === 'A' ? trigger.getAttribute('href') : null);
                var form = trigger.closest('form');

                if (href && href !== '#') {
                    window.location.href = href;
                } else if (form) {
                    form.submit();
                }
            },
        });
    });
})();
</script>
