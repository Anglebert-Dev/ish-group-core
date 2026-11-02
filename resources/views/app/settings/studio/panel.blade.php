{{--
    Branding studio (docs/branding.md §3.15): the real pages with the form's
    unsaved choices (?studio=, honoured only for people who may edit), at
    phone, tablet or desktop size, light or dark. Nothing is saved until
    "Save draft"; nobody else sees it.
--}}
@php
    $studioPages = [
        'dashboard' => route('dashboard'),
        'list' => route('customers.index'),
        'form' => route('customers.create'),
        'components' => route('settings.studio.components'),
        'login' => route('settings.studio.login'),
        'email' => route('settings.studio.email'),
        'document' => route('settings.print-preview'),
    ];
@endphp
<div id="studio" class="2xl:sticky 2xl:top-4 bg-card border border-line rounded-xl overflow-hidden" data-switches='@json(array_keys(\App\Support\Branding\ThemeSchema::SWITCHES))'>
    <div class="px-4 py-3 border-b border-line flex flex-wrap items-center gap-2 justify-between">
        <p class="font-semibold text-ink text-sm">{{ __('body.studio_title') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <select id="studio-page" class="h-9 rounded-lg border border-line bg-card px-2 text-sm text-body" aria-label="{{ __('body.studio_page') }}">
                @foreach ($studioPages as $key => $url)
                    <option value="{{ $url }}">{{ __('body.studio_page_'.$key) }}</option>
                @endforeach
            </select>
            <div class="inline-flex rounded-lg border border-line overflow-hidden" role="group" aria-label="{{ __('body.studio_device') }}">
                @foreach (['390' => 'phone', '820' => 'tablet', '1280' => 'desktop'] as $width => $device)
                    <button type="button" data-device="{{ $width }}" class="px-2.5 h-9 text-xs font-semibold text-body hover:bg-surface aria-pressed:bg-brand aria-pressed:text-on-brand" aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}">{{ __('body.studio_device_'.$device) }}</button>
                @endforeach
            </div>
            <div class="inline-flex rounded-lg border border-line overflow-hidden" role="group" aria-label="{{ __('body.appearance') }}">
                @foreach (['light', 'dark'] as $appearance)
                    <button type="button" data-appearance="{{ $appearance }}" class="px-2.5 h-9 text-xs font-semibold text-body hover:bg-surface aria-pressed:bg-brand aria-pressed:text-on-brand" aria-pressed="{{ $appearance === 'light' ? 'true' : 'false' }}">{{ __('body.appearance_'.$appearance) }}</button>
                @endforeach
            </div>
        </div>
    </div>
    <div id="studio-stage" class="bg-line/40 overflow-hidden">
        <iframe id="studio-frame" title="{{ __('body.studio_title') }}" class="block origin-top-left bg-page" style="border: 0; width: 1280px; height: 900px"></iframe>
    </div>
    <p class="px-4 py-2 text-xs text-muted border-t border-line">{{ __('body.studio_hint') }}</p>
</div>

<script>
    (function () {
        var studio = document.getElementById('studio');
        var form = studio.closest('form');
        var frame = document.getElementById('studio-frame');
        var stage = document.getElementById('studio-stage');
        var page = document.getElementById('studio-page');
        var switches = JSON.parse(studio.dataset.switches);
        var device = 1280, appearance = 'light', timer;

        // The form, as the theme document's sections.
        var SECTIONS = ['colors', 'colors_dark', 'layout', 'switches', 'mode', 'heading_font', 'user_mode', 'dashboard', 'menu', 'announcement', 'landing', 'custom_css'];

        // "a[b][c]" names → nested objects, as PHP reads them ("a[b][]" lists are handled in payload()).
        function put(data, name, value) {
            var keys = name.replace(/\]/g, '').split('['), node = data;
            for (var i = 0; i < keys.length - 1; i++) node = node[keys[i]] = node[keys[i]] || {};
            node[keys[keys.length - 1]] = value;
        }

        function payload() {
            var data = {};
            new FormData(form).forEach(function (value, name) {
                if (SECTIONS.indexOf(name.split('[')[0]) === -1) return;
                if (/\[\]$/.test(name)) {
                    var base = name.slice(0, -2), keys = base.replace(/\]/g, '').split('['), node = data;
                    for (var i = 0; i < keys.length - 1; i++) node = node[keys[i]] = node[keys[i]] || {};
                    (node[keys[keys.length - 1]] = node[keys[keys.length - 1]] || []).push(value);
                } else {
                    put(data, name, value);
                }
            });
            if (data.switches) Object.keys(data.switches).forEach(function (k) { data.switches[k] = data.switches[k] === '1'; });
            if ('user_mode' in data) data.user_mode = data.user_mode === '1';
            if (form.querySelector('[name^="switches["]')) {
                data.switches = data.switches || {};
                switches.forEach(function (key) { if (!(key in data.switches)) data.switches[key] = false; });
            }
            return data;
        }

        function encode(data) {
            return btoa(unescape(encodeURIComponent(JSON.stringify(data)))).replace(/\+/g, '-').replace(/\//g, '_');
        }

        function fit() {
            var scale = Math.min(1, stage.clientWidth / device);
            frame.style.width = device + 'px';
            frame.style.transform = 'scale(' + scale + ')';
            frame.style.marginLeft = Math.max(0, (stage.clientWidth - device * scale) / 2) + 'px';
            stage.style.height = Math.round(900 * scale) + 'px';
        }

        function refresh() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var url = page.value;
                frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'studio=' + encodeURIComponent(encode(payload())) + '&appearance=' + appearance;
            }, 350);
        }

        function press(selector, button) {
            studio.querySelectorAll(selector).forEach(function (b) { b.setAttribute('aria-pressed', b === button ? 'true' : 'false'); });
        }

        studio.querySelectorAll('[data-device]').forEach(function (b) {
            b.addEventListener('click', function () { device = +b.dataset.device; press('[data-device]', b); fit(); });
        });
        studio.querySelectorAll('[data-appearance]').forEach(function (b) {
            b.addEventListener('click', function () { appearance = b.dataset.appearance; press('[data-appearance]', b); refresh(); });
        });
        page.addEventListener('change', refresh);
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        window.addEventListener('resize', fit);
        fit();
        refresh();
    })();
</script>
