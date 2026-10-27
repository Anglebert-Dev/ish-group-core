<div id="toast-stack" class="fixed top-4 inset-x-4 sm:inset-x-auto sm:right-5 z-[100] flex flex-col gap-3 w-auto sm:w-[calc(100%-2.5rem)] max-w-sm pointer-events-none"></div>

<script>
(function () {
    var stack = document.getElementById('toast-stack');

    var variants = {
        success: { border: 'border-success', icon: 'text-success', path: 'M5 13l4 4L19 7' },
        error:   { border: 'border-danger',  icon: 'text-danger',  path: 'M6 18L18 6M6 6l12 12' },
        warning: { border: 'border-warning', icon: 'text-warning', path: 'M12 9v4m0 4h.01M10.29 3.86L2.11 18.05A2 2 0 003.93 21h16.14a2 2 0 001.82-2.95L13.71 3.86a2 2 0 00-3.42 0z' },
        info:    { border: 'border-brand',   icon: 'text-brand',   path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    };

    window.toast = function (message, type, duration) {
        type = variants[type] ? type : 'info';
        duration = duration || 4500;
        var v = variants[type];

        var el = document.createElement('div');
        el.className = 'pointer-events-auto flex items-start gap-3 bg-white border border-line ' + v.border + ' border-l-4 rounded-lg shadow-lg p-4 opacity-0 -translate-y-2 transition-all duration-300';
        el.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 ' + v.icon + '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="' + v.path + '"/>' +
            '</svg>' +
            '<p class="text-sm text-body flex-1 leading-snug"></p>' +
            '<button type="button" class="text-muted hover:text-ink shrink-0" aria-label="Dismiss">' +
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
            '</button>';

        el.querySelector('p').textContent = message;
        stack.appendChild(el);

        requestAnimationFrame(function () {
            el.classList.remove('opacity-0', '-translate-y-2');
        });

        function dismiss() {
            el.classList.add('opacity-0', '-translate-y-2');
            setTimeout(function () { el.remove(); }, 300);
        }

        el.querySelector('button').addEventListener('click', dismiss);
        setTimeout(dismiss, duration);
    };

    @foreach (['success', 'error', 'warning', 'info'] as $flashType)
        @if (session($flashType))
            window.toast(@json(session($flashType)), '{{ $flashType }}');
        @endif
    @endforeach
})();
</script>
