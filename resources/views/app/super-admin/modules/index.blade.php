<x-layouts.app :title="__('body.modules')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.modules')" :description="__('body.modules_description')" />

    @php
        $filters = [
            'all' => [__('body.module_filter_all'), $modules->count()],
            'active' => [__('body.active'), $modules->where('active', true)->count()],
            'update' => [__('body.module_filter_updates'), $modules->where('update_available', true)->count()],
            'off' => [__('body.disabled'), $modules->where('installed', true)->where('active', false)->count()],
            'available' => [__('body.not_installed'), $modules->where('installed', false)->count()],
        ];
    @endphp

    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-6" data-module-filters>
        <input type="search" data-module-search placeholder="{{ __('body.module_search') }}" aria-label="{{ __('body.module_search') }}"
               class="h-10 w-full lg:w-72 rounded-lg border border-line bg-card px-3 text-sm text-ink">
        <div class="flex flex-wrap gap-2">
            @foreach ($filters as $key => [$label, $count])
                <button type="button" data-module-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}"
                        class="h-9 px-3 rounded-lg border text-sm font-medium aria-pressed:border-brand aria-pressed:text-brand border-line text-body">
                    {{ $label }} <span class="text-muted">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="space-y-8">
        @forelse ($categories as $key => $group)
            <section data-module-section>
                <h2 class="font-display font-bold text-ink mb-3">{{ \Illuminate\Support\Facades\Lang::has('body.module_category_'.$key) ? __('body.module_category_'.$key) : \Illuminate\Support\Str::headline($key) }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($group as $module)
                        @include('app.super-admin.modules.partials.card')
                    @endforeach
                </div>
            </section>
        @empty
            <x-common.empty-state :title="__('body.no_modules_found')" />
        @endforelse

        <p data-module-none class="hidden py-10 text-center text-sm text-muted">{{ __('body.module_search_none') }}</p>
    </div>

    <p class="text-sm text-muted mt-8">{{ __('body.module_billing_hint') }}</p>
    <p class="text-sm text-muted mt-2">{{ __('body.modules_cli_hint') }}</p>

    <script>
    (function () {
        var search = document.querySelector('[data-module-search]');
        var buttons = document.querySelectorAll('[data-module-filter]');
        var state = 'all';

        function apply() {
            var term = search.value.trim().toLowerCase();
            var shown = 0;
            document.querySelectorAll('[data-module-section]').forEach(function (section) {
                var inSection = 0;
                section.querySelectorAll('[data-module-card]').forEach(function (card) {
                    var cardState = card.getAttribute('data-state');
                    // An update waiting still means the module is running.
                    var matches = (state === 'all' || cardState === state || (state === 'active' && cardState === 'update'))
                        && card.getAttribute('data-search').indexOf(term) !== -1;
                    card.classList.toggle('hidden', !matches);
                    if (matches) inSection++;
                });
                section.classList.toggle('hidden', inSection === 0);
                shown += inSection;
            });
            document.querySelector('[data-module-none]').classList.toggle('hidden', shown > 0);
        }

        search.addEventListener('input', apply);
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                state = button.getAttribute('data-module-filter');
                buttons.forEach(function (other) { other.setAttribute('aria-pressed', other === button ? 'true' : 'false'); });
                apply();
            });
        });
    })();
    </script>
</x-layouts.app>
