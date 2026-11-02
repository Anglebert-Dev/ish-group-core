<x-layouts.app :title="$site->name">
    @include('distribution::partials.nav')

    @php
        $paidStatus = \Modules\Distribution\Enums\ChargeStatus::Paid;
        // One line each for the strip at the top and the overview.
        [$supportText, $supportColor] = match (true) {
            $site->is_test => [__('distribution::distribution.support_test_site'), 'muted'],
            ! $site->support_until => [__('distribution::distribution.support_not_started'), 'warning'],
            ! $site->hasSupport() => [__('distribution::distribution.support_ended_on', ['date' => $site->support_until->format('d M Y')]), 'danger'],
            default => [__('distribution::distribution.support_active_until', ['date' => $site->support_until->format('d M Y')]), $site->supportDaysLeft() <= 30 ? 'warning' : 'success'],
        };
        [$licenceText, $licenceColor] = match (true) {
            $site->is_test => [__('distribution::distribution.licence_not_needed'), 'muted'],
            ! $licence => [__('distribution::distribution.licence_none'), 'warning'],
            $licence->status === $paidStatus => [__('distribution::distribution.licence_paid_on', ['date' => $licence->paid_on?->format('d M Y'), 'method' => $licence->payment_method ? __('distribution::distribution.method_'.$licence->payment_method) : '—', 'reference' => $licence->payment_reference ?: '—']), 'success'],
            default => [__('distribution::distribution.licence_waiting'), $licence->due_on?->isPast() ? 'danger' : 'warning'],
        };
    @endphp

    <x-common.page-header :title="$site->name" :description="$site->customer_name">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.distribution.sites.edit', $site) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    @if ($site->is_test)
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-5 py-4 mb-4 text-sm text-ink">
            <p class="font-semibold">{{ __('distribution::distribution.test_site_banner_title') }}</p>
            <p>{{ __('distribution::distribution.test_site_banner_body') }}</p>
        </div>
    @endif

    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm mb-4">
        <span class="flex items-center gap-2"><span class="text-muted">{{ __('body.status') }}</span><x-common.badge :color="$site->status->color()">{{ $site->status->label() }}</x-common.badge></span>
        <span class="flex items-center gap-2"><span class="text-muted">{{ __('distribution::distribution.licence_card') }}</span><x-common.badge :color="$licenceColor">{{ $site->is_test || ! $licence || $licence->status === $paidStatus ? $licenceText : $licenceText.' · '.$licence->number }}</x-common.badge></span>
        <span class="flex items-center gap-2"><span class="text-muted">{{ __('distribution::distribution.support_card') }}</span><x-common.badge :color="$supportColor">{{ $supportText }}</x-common.badge></span>
        <span class="flex items-center gap-2"><span class="text-muted">{{ __('distribution::distribution.last_check_in') }}</span><span>{{ $site->last_check_in_at?->diffForHumans() ?? __('distribution::distribution.never') }}</span></span>
    </div>

    <div data-details-page class="bg-card border border-line rounded-xl overflow-hidden">
        <x-common.details.tabs class="rounded-t-xl" :active="$tab" :tabs="[
            ['id' => 'overview', 'label' => __('distribution::distribution.tab_overview')],
            ['id' => 'licence', 'label' => __('distribution::distribution.tab_licence'), 'badge' => ! $site->is_test && (! $licence || $licence->isOpen() || $renewal) ? '!' : null],
            ['id' => 'modules', 'label' => __('distribution::distribution.tab_modules')],
            ['id' => 'install', 'label' => __('distribution::distribution.tab_install')],
            ['id' => 'updates', 'label' => __('distribution::distribution.tab_updates')],
            ['id' => 'connection', 'label' => __('distribution::distribution.tab_connection')],
        ]" />

        @foreach (['overview', 'licence', 'modules', 'install', 'updates', 'connection'] as $panel)
            <div data-details-panel="{{ $panel }}" class="{{ $panel === $tab ? '' : 'hidden' }}">
                @include('distribution::sites.tabs.'.$panel)
            </div>
        @endforeach
    </div>
</x-layouts.app>
