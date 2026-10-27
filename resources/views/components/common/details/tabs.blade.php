@props([
    'tabs' => [],
    'active' => null,
])

@php
    $active = $active ?? ($tabs[0]['id'] ?? null);
@endphp

<div
    {{ $attributes->merge(['class' => 'border-b border-line bg-white px-2 sm:px-4']) }}
    data-details-tabs
    data-active-tab="{{ $active }}"
>
    <nav class="flex overflow-x-auto overscroll-x-contain gap-1 [scrollbar-width:thin]" aria-label="{{ __('body.details') }}">
        @foreach ($tabs as $tab)
            @php
                $id = $tab['id'];
                $label = $tab['label'];
                $isActive = $id === $active;
            @endphp
            <button
                type="button"
                data-details-tab="{{ $id }}"
                class="flex items-center gap-1.5 sm:gap-2 py-3 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm whitespace-nowrap transition-colors {{ $isActive ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-line' }}"
            >
                @if (! empty($tab['icon']))
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/></svg>
                @endif
                <span>{{ $label }}</span>
                @if (! empty($tab['badge']))
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-warning text-white text-[10px] font-bold">{{ $tab['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>
</div>

@once
<script>
(function () {
    function activateTab(root, tabId, pushUrl) {
        root.setAttribute('data-active-tab', tabId);
        root.querySelectorAll('[data-details-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-details-tab') === tabId;
            btn.classList.toggle('border-brand', active);
            btn.classList.toggle('text-brand', active);
            btn.classList.toggle('border-transparent', !active);
            btn.classList.toggle('text-muted', !active);
            if (!active) {
                btn.classList.add('hover:text-ink');
                btn.classList.add('hover:border-line');
            }
        });
        var scope = root.closest('[data-details-page]') || document;
        scope.querySelectorAll('[data-details-panel]').forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-details-panel') !== tabId);
        });
        if (pushUrl) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            } catch (e) {}
        }
    }

    function initTabs(root) {
        if (root.getAttribute('data-tabs-ready') === '1') return;
        root.setAttribute('data-tabs-ready', '1');
        var initial = root.getAttribute('data-active-tab');
        try {
            var fromUrl = new URL(window.location.href).searchParams.get('tab');
            if (fromUrl && root.querySelector('[data-details-tab="' + fromUrl + '"]')) {
                initial = fromUrl;
            }
        } catch (e) {}
        activateTab(root, initial, false);
        root.querySelectorAll('[data-details-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateTab(root, btn.getAttribute('data-details-tab'), true);
            });
        });
    }

    function boot() {
        document.querySelectorAll('[data-details-tabs]').forEach(initTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endonce
