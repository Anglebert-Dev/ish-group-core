<x-frontend::layouts.public :title="__('body.privacy_policy')">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.legal') }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-3">{{ __('body.privacy_policy') }}</h1>
        <p class="text-sm text-muted mb-8">{{ __('body.legal_last_updated', ['date' => '11 Sep 2026']) }}</p>

        <div class="space-y-8 text-body leading-relaxed">
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_intro_title') }}</h2>
                <p>{{ __('body.privacy_section_intro', ['company' => $company]) }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_collect_title') }}</h2>
                <p>{{ __('body.privacy_section_collect') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_use_title') }}</h2>
                <p>{{ __('body.privacy_section_use') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_share_title') }}</h2>
                <p>{{ __('body.privacy_section_share') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_rights_title') }}</h2>
                <p>{{ __('body.privacy_section_rights', ['company' => $company]) }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.privacy_section_contact_title') }}</h2>
                <p>{{ __('body.privacy_section_contact') }}</p>
            </section>
        </div>
    </div>
</x-frontend::layouts.public>
