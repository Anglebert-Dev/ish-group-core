@php $page = $site->get('pages.contact'); @endphp

<x-frontend::layouts.public :title="$page['title']">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ $page['tagline'] }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-4">{{ $page['title'] }}</h1>
        <p class="text-muted leading-relaxed max-w-2xl mb-10">{{ $page['lead'] }}</p>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
            <div class="lg:col-span-2">
                <x-frontend::contact-details :contact="$site->get('contact')" class="grid-cols-1" />
            </div>
            <div class="lg:col-span-3">
                <x-frontend::contact-form />
            </div>
        </div>
    </div>
</x-frontend::layouts.public>
