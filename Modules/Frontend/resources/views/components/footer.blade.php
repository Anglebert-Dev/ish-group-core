<footer class="bg-ink text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 grid grid-cols-1 md:grid-cols-4 gap-10">
        <div class="md:col-span-1">
            <x-common.logo dark />
            <p class="text-sm text-slate-400 mt-4 leading-relaxed">
                {{ $site->get('footer.blurb') }}
            </p>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.company') }}</p>
            <ul class="space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('site.home') }}" class="hover:text-white">{{ __('body.home') }}</a></li>
                @if ($site->get('header.show_about'))
                    <li><a href="{{ route('about') }}" class="hover:text-white">{{ __('body.about_us') }}</a></li>
                @endif
                @if ($site->get('header.show_services'))
                    <li><a href="{{ route('services') }}" class="hover:text-white">{{ __('body.services') }}</a></li>
                @endif
                @if ($site->get('header.show_contact'))
                    <li><a href="{{ route('contact') }}" class="hover:text-white">{{ __('body.contact') }}</a></li>
                @endif
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.legal') }}</p>
            <ul class="space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('privacy') }}" class="hover:text-white">{{ $site->get('pages.privacy.title') }}</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-white">{{ $site->get('pages.terms.title') }}</a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold mb-4">{{ __('body.get_in_touch') }}</p>
            @php
                $contact = $site->get('contact');
                $social = array_filter($site->get('footer.social', []));
            @endphp
            <ul class="space-y-3 text-sm text-slate-400">
                @if (filled($contact['address']))
                    <li>{{ $contact['address'] }}</li>
                @endif
                @if (filled($contact['phone']))
                    <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}" class="hover:text-white">{{ $contact['phone'] }}</a></li>
                @endif
                @if (filled($contact['email']))
                    <li><a href="mailto:{{ $contact['email'] }}" class="hover:text-white">{{ $contact['email'] }}</a></li>
                @endif
                @if ($site->get('header.show_contact'))
                    <li><a href="{{ route('contact') }}" class="hover:text-white">{{ __('body.contact_us') }}</a></li>
                @endif
            </ul>
            @if ($social !== [])
                <div class="flex flex-wrap gap-3 mt-5 text-sm">
                    @foreach ($social as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-white">{{ __('body.social_'.$network) }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col gap-4 text-sm text-slate-400">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                <p>&copy; {{ date('Y') }} {{ $branding->companyName() }}. {{ __('body.all_rights_reserved') }}</p>
                <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">{{ $site->get('pages.privacy.title') }}</a>
                    <a href="{{ route('terms') }}" class="hover:text-white transition-colors">{{ $site->get('pages.terms.title') }}</a>
                </div>
            </div>
        </div>
    </div>
</footer>
