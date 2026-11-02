<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-common.brand-head :title="__('billing::billing.pricing_title')" />
    <x-common.tailwind />
</head>
<body class="bg-surface text-body antialiased font-sans">
    <header class="bg-card border-b border-line">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <x-common.logo :href="route('pricing')" />
            <div class="flex items-center gap-2">
                <x-common.button href="{{ route('staff.login') }}" variant="secondary" size="sm">{{ __('body.log_in') }}</x-common.button>
                @if ($registrationOpen)
                    <x-common.button href="{{ route('staff.register') }}" size="sm">{{ __('body.sign_up') }}</x-common.button>
                @endif
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-14">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h1 class="font-display font-bold text-3xl sm:text-4xl text-ink">{{ __('billing::billing.pricing_title') }}</h1>
            <p class="text-muted mt-3">{{ __('billing::billing.pricing_subtitle') }}</p>
        </div>

        @if ($packages->isEmpty())
            <x-common.empty-state :title="__('billing::billing.no_packages')" />
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($packages as $package)
                    <x-billing::package-card :package="$package"
                        :action="$registrationOpen ? route('staff.register', ['package' => $package->uuid]) : null"
                        :action-label="$package->trial_days > 0 ? __('billing::billing.start_trial') : __('billing::billing.get_started')" />
                @endforeach
            </div>
            @unless ($registrationOpen)
                <p class="text-center text-sm text-muted mt-8">{{ __('billing::billing.pricing_closed') }}</p>
            @endunless
        @endif
    </main>
</body>
</html>
