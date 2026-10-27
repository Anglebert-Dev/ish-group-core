@php $page = $site->get('pages.about'); @endphp

<x-frontend::layouts.public :title="$page['title']">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ $page['tagline'] }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-6">{{ $page['title'] }}</h1>
        <div class="space-y-4 text-body leading-relaxed">
            <x-frontend::paragraphs :text="$page['body']" />
        </div>
    </div>
</x-frontend::layouts.public>
