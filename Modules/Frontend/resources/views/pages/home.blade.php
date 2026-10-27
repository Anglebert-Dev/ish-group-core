@php
    $hero = $site->get('home.hero');
    $features = $site->get('home.features');
    $about = $site->get('home.about');
    $cta = $site->get('home.cta');
    $contactSection = $site->get('home.contact');
    $contact = $site->get('contact');
    $canRegister = (bool) $site->get('general.allow_registration');
@endphp

<x-frontend::layouts.public :title="$site->get('general.seo_title')" :raw-title="true">

    @if ($hero['show'])

    {{-- HERO --}}
    <section id="home" class="relative bg-[#02070f] flex flex-col lg:min-h-[80svh]">
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 bg-[#02070f]"></div>
            <div class="absolute inset-0 hero-glow"></div>
            <div class="absolute left-1/2 top-[45%] -translate-x-1/2 -translate-y-1/2 w-[min(92vw,780px)] h-[min(92vw,780px)] rounded-full"
                 style="background: radial-gradient(circle, rgba(210,232,255,0.35) 0%, rgba(90,160,230,0.16) 34%, rgba(10,30,55,0) 68%);"></div>
        </div>

        <div class="relative z-10 flex flex-col flex-1 items-center justify-center gap-5 px-4 sm:px-6 pt-32 pb-20 text-center">
            <p class="text-brand text-[11px] sm:text-xs font-semibold tracking-[0.22em] uppercase">
                {{ $hero['tagline'] }}
            </p>
            <h1 class="font-display font-bold text-white uppercase tracking-[0.06em] text-[1.75rem] sm:text-4xl lg:text-5xl xl:text-[3.2rem] leading-[1.12] max-w-4xl">
                {{ $hero['title'] }}
            </h1>
            <p class="text-slate-300 text-base sm:text-lg max-w-2xl leading-relaxed">
                {{ $hero['subtitle'] }}
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-2">
                @auth('customer')
                    <x-common.button href="{{ route('profile') }}">{{ __('body.my_account') }}</x-common.button>
                @else
                    @if ($canRegister && filled($hero['primary_label']))
                        <x-common.button href="{{ route('register') }}">{{ $hero['primary_label'] }}</x-common.button>
                    @endif
                    @if (filled($hero['secondary_label']))
                        <x-common.button href="{{ route('login') }}" variant="secondary">{{ $hero['secondary_label'] }}</x-common.button>
                    @endif
                @endauth
            </div>
        </div>
    </section>
    @endif

    @if ($features['show'])
    <section class="bg-white border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ $features['tagline'] }}</p>
                <h2 class="font-display font-bold text-3xl sm:text-4xl text-ink leading-tight tracking-tight">{{ $features['title'] }}</h2>
                <p class="text-muted mt-5 text-base sm:text-lg leading-relaxed">
                    {{ $features['description'] }}
                </p>
            </div>

            <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-10">
                @foreach ($features['items'] as $item)
                    <div class="border-t border-ink/10 pt-5">
                        <p class="text-brand font-display font-bold text-sm tracking-widest mb-3">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                        <p class="font-display font-bold text-ink text-lg">{{ $item['title'] ?? '' }}</p>
                        <p class="text-sm text-muted mt-2 leading-relaxed">{{ $item['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if ($about['show'])
    <section id="about" class="scroll-mt-24 bg-ink text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-end">
                <div class="lg:col-span-7">
                    <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ $about['tagline'] }}</p>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-5xl leading-tight tracking-tight">{{ $about['title'] }}</h2>
                    <p class="text-slate-300 mt-6 text-base sm:text-lg leading-relaxed max-w-2xl">
                        {{ $about['description'] }}
                    </p>
                </div>
            </div>
        </div>
    </section>
    @endif

    @if ($cta['show'] && $canRegister)
    <section class="bg-[#f4f6f8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="rounded-[1.75rem] bg-brand px-5 py-8 sm:px-12 sm:py-12 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 shadow-lg shadow-brand/20">
                <div>
                    <h2 class="font-display font-bold text-2xl sm:text-3xl text-white tracking-tight">{{ $cta['title'] }}</h2>
                    <p class="text-white/80 mt-2 max-w-md">{{ $cta['subtitle'] }}</p>
                </div>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-white text-brand hover:bg-brand-light font-semibold text-sm px-7 py-3.5 transition-colors shrink-0 w-full sm:w-auto">
                    {{ $cta['button'] }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endif

    @if ($contactSection['show'])
    <section id="contact" class="scroll-mt-24 bg-[#f4f6f8] border-t border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ $contactSection['tagline'] }}</p>
                <h2 class="font-display font-bold text-3xl sm:text-4xl text-ink tracking-tight">{{ $contactSection['title'] }}</h2>
                <p class="text-muted mt-5 leading-relaxed">{{ $contactSection['lead'] }}</p>
            </div>
            <div class="mt-10 grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
                <div class="lg:col-span-2"><x-frontend::contact-details :contact="$contact" class="grid-cols-1" /></div>
                <div class="lg:col-span-3"><x-frontend::contact-form /></div>
            </div>
        </div>
    </section>
    @endif

</x-frontend::layouts.public>
