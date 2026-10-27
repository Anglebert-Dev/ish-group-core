@php $page = $site->get('pages.terms'); @endphp

<x-frontend::layouts.public :title="$page['title']">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.legal') }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-3">{{ $page['title'] }}</h1>
        @if (filled($page['updated']))
            <p class="text-sm text-muted mb-8">{{ __('body.legal_last_updated', ['date' => $page['updated']]) }}</p>
        @endif

        <div class="space-y-8 text-body leading-relaxed mt-8">
            @foreach ($page['sections'] as $section)
                <section>
                    @if (filled($section['title'] ?? null))
                        <h2 class="font-display font-bold text-lg text-ink mb-2">{{ $section['title'] }}</h2>
                    @endif
                    <div class="space-y-3">
                        <x-frontend::paragraphs :text="$section['body'] ?? ''" />
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-frontend::layouts.public>
