<footer class="bg-ink text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 grid grid-cols-1 md:grid-cols-4 gap-10">
        <div class="md:col-span-1">
            <x-common.logo dark />
            <p class="text-sm text-slate-400 mt-4 leading-relaxed">
                {{ __('body.site_footer_blurb', ['company' => $company]) }}
            </p>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.company') }}</p>
            <ul class="space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('about') }}" class="hover:text-white">{{ __('body.about_us') }}</a></li>
                <li><a href="{{ route('services') }}" class="hover:text-white">{{ __('body.services') }}</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-white">{{ __('body.contact') }}</a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.legal') }}</p>
            <ul class="space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('privacy') }}" class="hover:text-white">{{ __('body.privacy_policy') }}</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-white">{{ __('body.terms_of_service') }}</a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.get_in_touch') }}</p>
            <ul class="space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('contact') }}" class="hover:text-white">{{ __('body.contact_us') }}</a></li>
                @if (config('mail.from.address'))
                    <li>{{ config('mail.from.address') }}</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col gap-4 text-sm text-slate-400">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                <p>&copy; {{ date('Y') }} {{ $branding->companyName() }}. {{ __('body.all_rights_reserved') }}</p>
                <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">{{ __('body.privacy_policy') }}</a>
                    <a href="{{ route('terms') }}" class="hover:text-white transition-colors">{{ __('body.terms_and_conditions') }}</a>
                </div>
                <p>{{ __('body.timezone_footer') }}</p>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                <p>{{ __('body.tagline_footer') }}</p>
                <a href="https://afriforge.rw" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 hover:text-white transition-colors">
                    <span>{{ __('body.powered_by') }}</span>
                    <img
                        src="{{ asset('assets/images/logo/afriforge.png') }}"
                        alt="{{ __('body.powered_by_afriforge') }}"
                        class="h-7 w-auto object-contain"
                    >
                </a>
            </div>
        </div>
    </div>
</footer>
