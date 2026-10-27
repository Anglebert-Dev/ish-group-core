<x-layouts.public :title="__('body.terms_and_conditions')">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.legal') }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-3">{{ __('body.terms_and_conditions') }}</h1>
        <p class="text-sm text-muted mb-8">{{ __('body.legal_last_updated', ['date' => '11 Sep 2026']) }}</p>

        <div class="space-y-8 text-body leading-relaxed">
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_intro_title') }}</h2>
                <p>{{ __('body.terms_section_intro', ['company' => $company]) }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_booking_title') }}</h2>
                <p>{{ __('body.terms_section_booking') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_payment_title') }}</h2>
                <p>{{ __('body.terms_section_payment') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_driver_title') }}</h2>
                <p>{{ __('body.terms_section_driver') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_cancel_title') }}</h2>
                <p>{{ __('body.terms_section_cancel') }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_liability_title') }}</h2>
                <p>{{ __('body.terms_section_liability', ['company' => $company]) }}</p>
            </section>
            <section>
                <h2 class="font-display font-bold text-lg text-ink mb-2">{{ __('body.terms_section_contact_title') }}</h2>
                <p>{{ __('body.terms_section_contact') }}</p>
            </section>
        </div>
    </div>
</x-layouts.public>
