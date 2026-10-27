<x-layouts.public :title="__('body.services')">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.services_tagline') }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-10">{{ __('body.services_title') }}</h1>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            @foreach ([
                ['title' => __('body.service_self_drive_title'), 'desc' => __('body.service_self_drive_desc')],
                ['title' => __('body.service_chauffeur_title'), 'desc' => __('body.service_chauffeur_desc')],
                ['title' => __('body.service_corporate_title'), 'desc' => __('body.service_corporate_desc')],
            ] as $s)
                <div class="border border-line rounded-xl p-6 bg-white">
                    <p class="font-semibold text-ink">{{ $s['title'] }}</p>
                    <p class="text-sm text-muted mt-2 leading-relaxed">{{ $s['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.public>
