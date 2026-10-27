@props([
    'title' => null,
    'eyebrow' => null,
    'heading' => null,
    'subtitle' => null,
    'panelText' => null,
    'panelImage' => null,
])

@php
    $panelText = $panelText ?: __('body.auth_panel_text');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <x-common.brand-head :title="$title ?? null" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { display: ['Inter', 'sans-serif'], sans: ['Inter', 'sans-serif'] },
            colors: {
                brand: { DEFAULT: 'var(--brand)', dark: 'var(--brand-dark)', light: 'var(--brand-light)' },
                ink: '#111827', body: '#1F2937', muted: '#6B7280', surface: '#F8FAFC', line: '#E5E7EB',
                success: '#16A34A', warning: '#F59E0B', danger: '#DC2626',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgba(17, 24, 39, 0.04)',
            },
        }}};
    </script>
    <style>
        body { font-family: Inter, sans-serif; }
        .font-display { font-family: Inter, sans-serif; }
        .auth-shell {
            background:
                radial-gradient(ellipse 80% 50% at 10% 0%, color-mix(in srgb, var(--brand) 12%, transparent), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, color-mix(in srgb, var(--brand) 8%, transparent), transparent 50%),
                #F3F4F6;
        }
        .auth-panel-frame {
            position: relative;
            border-radius: 1.5rem;
            overflow: hidden;
            min-height: 14rem;
            background: #02070f;
        }
        @media (min-width: 1024px) {
            .auth-panel-frame {
                min-height: 100%;
                border-radius: 1.75rem;
            }
            .auth-panel-frame::before,
            .auth-panel-frame::after {
                content: '';
                position: absolute;
                z-index: 2;
                width: 3.25rem;
                height: 3.25rem;
                background: #fff;
                pointer-events: none;
            }
            .auth-panel-frame::before {
                top: -0.05rem;
                left: -0.05rem;
                border-bottom-right-radius: 2rem;
            }
            .auth-panel-frame::after {
                right: -0.05rem;
                bottom: -0.05rem;
                border-top-left-radius: 2rem;
            }
        }
        .auth-input-soft input:not([type="checkbox"]):not([type="radio"]):not(.date-field),
        .auth-input-soft textarea,
        .auth-input-soft select {
            background: #F3F4F6 !important;
            border-color: transparent !important;
            box-shadow: none !important;
        }
        .auth-input-soft input:not([type="checkbox"]):not([type="radio"]):not(.date-field):hover,
        .auth-input-soft textarea:hover,
        .auth-input-soft select:hover {
            background: #ECEEF1 !important;
        }
        .auth-input-soft input:not([type="checkbox"]):not([type="radio"]):not(.date-field):focus,
        .auth-input-soft textarea:focus,
        .auth-input-soft select:focus {
            background: #fff !important;
            border-color: var(--brand) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 16%, transparent) !important;
        }
        .auth-input-soft input.is-password-weak,
        .auth-input-soft input.is-password-weak:focus {
            border-color: #DC2626 !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, #DC2626 16%, transparent) !important;
            background: #fff !important;
        }
        .auth-input-soft input.is-password-strong,
        .auth-input-soft input.is-password-strong:focus {
            border-color: #16A34A !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, #16A34A 16%, transparent) !important;
            background: #fff !important;
        }
        .auth-input-soft [data-phone-shell] {
            background: #F3F4F6 !important;
            border-color: transparent !important;
            box-shadow: none !important;
        }
        .auth-input-soft [data-phone-shell]:hover {
            background: #ECEEF1 !important;
        }
        .auth-input-soft [data-phone-shell]:focus-within {
            background: #fff !important;
            border-color: var(--brand) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 16%, transparent) !important;
        }
        .auth-input-soft [data-phone-toggle] {
            background: transparent !important;
        }
        .auth-input-soft input[type="checkbox"]:not(.sr-only) {
            appearance: auto;
            width: 1rem;
            height: 1rem;
            accent-color: var(--brand);
            cursor: pointer;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="auth-shell text-body antialiased min-h-screen flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10">

    <div class="w-full max-w-6xl bg-white rounded-[1.5rem] sm:rounded-[2rem] border border-white/80 shadow-[0_24px_60px_rgba(15,23,42,0.08)] overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 min-h-0 lg:min-h-[680px]">

            {{-- Form column --}}
            <div class="flex flex-col justify-center px-5 py-8 sm:px-10 lg:px-12 xl:px-14">
                <div class="mb-8">
                    <x-common.logo compact :href="route('home')" />
                </div>

                @if ($heading || $eyebrow || isset($header))
                    <div class="mb-7">
                        @isset($header)
                            {{ $header }}
                        @else
                            @if ($eyebrow)
                                <p class="text-xs sm:text-sm font-semibold uppercase tracking-[0.14em] text-brand mb-2">{{ $eyebrow }}</p>
                            @endif
                            @if ($heading)
                                <h1 class="font-display font-bold text-2xl sm:text-3xl text-ink tracking-tight">{{ $heading }}</h1>
                            @endif
                            @if ($subtitle)
                                <p class="text-sm sm:text-[15px] text-muted mt-2 leading-relaxed">{{ $subtitle }}</p>
                            @endif
                        @endisset
                    </div>
                @endif

                <div class="auth-input-soft w-full max-w-md">
                    {{ $slot }}
                </div>

                @isset($footer)
                    @if (! $footer->isEmpty())
                        <div class="mt-6 text-sm text-muted max-w-md">
                            {{ $footer }}
                        </div>
                    @endif
                @endisset
            </div>

            {{-- Visual panel --}}
            <div class="relative p-4 sm:p-5 lg:p-6 order-first lg:order-none">
                <div class="auth-panel-frame h-44 sm:h-56 lg:h-full bg-ink">
                    @if ($panelImage)
                        <img
                            src="{{ $panelImage }}"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover object-[center_72%] lg:object-center opacity-95"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/25 to-ink/40"></div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-brand via-brand-dark to-ink"></div>
                    @endif
                    <div class="relative z-10 h-full p-6 sm:p-8 lg:p-10 flex flex-col justify-between">
                        <p class="text-white font-display font-bold text-lg sm:text-xl lg:text-2xl leading-snug max-w-sm drop-shadow-sm">
                            {{ $panelText }}
                        </p>
                        <p class="text-white/70 text-xs sm:text-sm tracking-wide">
                            {{ $branding->companyName() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-common.toast />
    <x-common.confirm-modal />
    <script src="{{ asset('js/datetime-split.js') }}"></script>
    <script src="{{ asset('js/searchable-select.js') }}"></script>
    <script src="{{ asset('js/form-wizard.js') }}"></script>
    @stack('scripts')
</body>
</html>
