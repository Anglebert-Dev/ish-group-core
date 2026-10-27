@php
    // Form name for a dot path: "home.hero.title" → "home[hero][title]".
    $n = fn (string $path) => preg_replace('/\.([^.]+)/', '[$1]', $path);
    // Value for a field: old input, else the saved/default value (":company" kept as typed).
    $v = fn (string $path) => old($path, $site->raw($path));
    $tabLabels = [
        'general' => __('body.website_tab_general'),
        'home' => __('body.website_tab_home'),
        'pages' => __('body.website_tab_pages'),
        'layout' => __('body.website_tab_layout'),
    ];
@endphp

<x-layouts.app :title="__('body.website_edit')">
    <x-common.page-header :title="__('body.website_edit')" :description="__('body.website_edit_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('frontend.admin.website.view') }}" variant="secondary" size="sm" target="_blank">
                {{ $site->is_published ? __('body.website_view') : __('body.website_preview') }}
            </x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="flex items-center gap-3 mb-5 text-sm">
        <x-common.badge :color="$site->is_published ? 'success' : 'warning'">
            {{ $site->is_published ? __('body.website_published') : __('body.website_unpublished') }}
        </x-common.badge>
        <span class="text-muted break-all">{{ url('/s/'.$site->slug) }}</span>
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-line mb-6">
        @foreach ($tabs as $key)
            <a href="{{ route('frontend.admin.website.edit', $key) }}"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $tabLabels[$key] }}
            </a>
        @endforeach
    </nav>

    <form action="{{ route('frontend.admin.website.update', $tab) }}" method="POST" class="space-y-6 max-w-4xl">
        @csrf
        @method('PUT')

        @include('frontend::admin.website.tabs.'.$tab)

        <p class="text-xs text-muted">{{ __('body.website_company_placeholder_hint') }}</p>

        @if (\App\Helpers\AuthHelper::can('update website.site'))
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
        @endif
    </form>
</x-layouts.app>
