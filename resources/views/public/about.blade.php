<x-layouts.public :title="__('body.about_us')">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.about_tagline', ['company' => $company]) }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-5">{{ __('body.about_title') }}</h1>
        <p class="text-body leading-relaxed">
            {{ __('body.about_description', ['company' => $company]) }}
        </p>
    </div>
</x-layouts.public>
