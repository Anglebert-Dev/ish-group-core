@php $page = $site->get('pages.services'); @endphp

<x-frontend::layouts.public :title="$page['title']">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ $page['tagline'] }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-10">{{ $page['title'] }}</h1>
        @if ($page['items'] === [])
            <p class="text-muted">{{ __('body.no_services_yet') }}</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($page['items'] as $item)
                    <div class="border border-line rounded-xl p-6 bg-white">
                        <p class="font-semibold text-ink">{{ $item['title'] ?? '' }}</p>
                        <p class="text-sm text-muted mt-2 leading-relaxed">{{ $item['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-frontend::layouts.public>
