<x-frontend::layouts.public>

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
                {{ __('body.hero_tagline', ['company' => $company]) }}
            </p>
            <h1 class="font-display font-bold text-white uppercase tracking-[0.06em] text-[1.75rem] sm:text-4xl lg:text-5xl xl:text-[3.2rem] leading-[1.12] max-w-4xl">
                {{ __('body.hero_title', ['company' => $company]) }}
            </h1>
            <p class="text-slate-300 text-base sm:text-lg max-w-2xl leading-relaxed">
                {{ __('body.hero_subtitle') }}
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-2">
                @auth('customer')
                    <x-common.button href="{{ route('profile') }}">{{ __('body.my_account') }}</x-common.button>
                @else
                    <x-common.button href="{{ route('register') }}">{{ __('body.get_started') }}</x-common.button>
                    <x-common.button href="{{ route('login') }}" variant="secondary">{{ __('body.log_in') }}</x-common.button>
                @endauth
            </div>
        </div>
    </section>

    {{-- WHY CHOOSE US --}}
    <section class="bg-white border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ __('body.why_choose_tagline', ['company' => $company]) }}</p>
                <h2 class="font-display font-bold text-3xl sm:text-4xl text-ink leading-tight tracking-tight">{{ __('body.why_choose_title') }}</h2>
                <p class="text-muted mt-5 text-base sm:text-lg leading-relaxed">
                    {{ __('body.why_choose_description', ['company' => $company]) }}
                </p>
            </div>

            <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-10">
                @foreach ([
                    ['n' => '01', 'title' => __('body.feature_reliable_title'), 'desc' => __('body.feature_reliable_desc')],
                    ['n' => '02', 'title' => __('body.feature_secure_title'), 'desc' => __('body.feature_secure_desc')],
                    ['n' => '03', 'title' => __('body.feature_easy_access_title'), 'desc' => __('body.feature_easy_access_desc')],
                    ['n' => '04', 'title' => __('body.feature_support_title'), 'desc' => __('body.feature_support_desc')],
                ] as $item)
                    <div class="border-t border-ink/10 pt-5">
                        <p class="text-brand font-display font-bold text-sm tracking-widest mb-3">{{ $item['n'] }}</p>
                        <p class="font-display font-bold text-ink text-lg">{{ $item['title'] }}</p>
                        <p class="text-sm text-muted mt-2 leading-relaxed">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="about" class="scroll-mt-24 bg-ink text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-end">
                <div class="lg:col-span-7">
                    <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ __('body.about_tagline', ['company' => $company]) }}</p>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-5xl leading-tight tracking-tight">{{ __('body.about_title') }}</h2>
                    <p class="text-slate-300 mt-6 text-base sm:text-lg leading-relaxed max-w-2xl">
                        {{ __('body.about_description', ['company' => $company]) }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-[#f4f6f8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="rounded-[1.75rem] bg-brand px-5 py-8 sm:px-12 sm:py-12 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 shadow-lg shadow-brand/20">
                <div>
                    <h2 class="font-display font-bold text-2xl sm:text-3xl text-white tracking-tight">{{ __('body.cta_title') }}</h2>
                    <p class="text-white/80 mt-2 max-w-md">{{ __('body.cta_subtitle') }}</p>
                </div>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-white text-brand hover:bg-brand-light font-semibold text-sm px-7 py-3.5 transition-colors shrink-0 w-full sm:w-auto">
                    {{ __('body.get_started') }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- CONTACT --}}
    <section id="contact" class="scroll-mt-24 bg-[#f4f6f8] border-t border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-start">
                <div class="lg:col-span-5">
                    <p class="text-brand text-xs font-semibold tracking-[0.18em] uppercase mb-4">{{ __('body.contact_tagline') }}</p>
                    <h2 class="font-display font-bold text-3xl sm:text-4xl text-ink tracking-tight">{{ __('body.contact_title') }}</h2>
                    <p class="text-muted mt-5 leading-relaxed max-w-md">{{ __('body.contact_lead') }}</p>

                    <ul class="mt-10 space-y-3">
                        <li class="flex items-start gap-3.5 rounded-2xl bg-white border border-line px-4 py-3.5">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('body.contact_location_label') }}</p>
                                <p class="mt-0.5 text-ink font-semibold">{{ __('body.contact_location_value') }}</p>
                            </div>
                        </li>
                        <li>
                            <a href="tel:{{ preg_replace('/\s+/', '', __('body.contact_phone_value')) }}"
                               class="flex items-start gap-3.5 rounded-2xl bg-white border border-line px-4 py-3.5 hover:border-brand/40 transition-colors">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('body.contact_phone_label') }}</p>
                                    <p class="mt-0.5 text-ink font-semibold">{{ __('body.contact_phone_value') }}</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:{{ __('body.contact_email_value') }}"
                               class="flex items-start gap-3.5 rounded-2xl bg-white border border-line px-4 py-3.5 hover:border-brand/40 transition-colors">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('body.contact_email_label') }}</p>
                                    <p class="mt-0.5 text-ink font-semibold truncate">{{ __('body.contact_email_value') }}</p>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-7">
                    <form class="relative overflow-hidden rounded-3xl border border-line bg-white p-6 sm:p-8 lg:p-9 shadow-sm shadow-slate-200/60 space-y-6" onsubmit="return false;">
                        <div class="absolute inset-x-0 top-0 h-1 bg-brand"></div>

                        <div>
                            <h3 class="font-display font-bold text-xl text-ink tracking-tight">{{ __('body.contact_form_title') }}</h3>
                            <p class="text-sm text-muted mt-1.5 leading-relaxed">{{ __('body.contact_form_subtitle') }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-common.input type="text" name="contact_name" :label="__('body.full_name')" :placeholder="__('body.contact_name_placeholder')" autocomplete="name" />
                            <x-common.input type="email" name="contact_email" :label="__('body.email_address')" :placeholder="__('body.contact_email_placeholder')" autocomplete="email" />
                        </div>
                        <x-common.textarea name="contact_message" :label="__('body.message')" :rows="5" :placeholder="__('body.contact_message_placeholder')" />

                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 pt-1">
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] px-6 py-3 transition-colors shadow-sm shadow-brand/25 w-full sm:w-auto">
                                {{ __('body.send_message') }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                            <p class="text-xs text-muted sm:max-w-[16rem] leading-relaxed">{{ __('body.contact_form_note') }}</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

</x-frontend::layouts.public>
