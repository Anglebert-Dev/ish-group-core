@php use Modules\Billing\Support\Money; @endphp
@props(['package', 'current' => false, 'action' => null, 'actionLabel' => null, 'admin' => false])

{{-- Pricing card (plans page and superadmin package list). --}}
<div class="relative flex flex-col bg-white border {{ $current ? 'border-brand ring-2 ring-brand/20' : 'border-line' }} rounded-2xl overflow-hidden">
    <div class="h-1 {{ $package->is_active ? 'bg-success' : 'bg-line' }}"></div>
    <div class="p-6 text-center border-b border-line">
        <div class="flex items-center justify-center gap-2">
            <h3 class="font-display font-bold text-lg text-ink">{{ $package->name }}</h3>
            @if ($package->is_popular)
                <x-common.badge color="success">{{ __('billing::billing.popular') }}</x-common.badge>
            @endif
        </div>
        @if ($admin)
            <div class="flex flex-wrap justify-center gap-1.5 mt-2">
                <x-common.badge :color="$package->is_active ? 'success' : 'muted'">{{ $package->is_active ? __('body.active') : __('body.inactive') }}</x-common.badge>
                @if ($package->is_private)<x-common.badge color="warning">{{ __('billing::billing.private') }}</x-common.badge>@endif
                @if (! empty($package->business_ids))<x-common.badge color="brand">{{ __('billing::billing.selected_businesses') }}</x-common.badge>@endif
            </div>
        @endif
        @if ($current)
            <p class="text-xs font-semibold text-brand mt-2">{{ __('billing::billing.current_plan') }}</p>
        @endif
    </div>

    <div class="p-6 flex-1 text-center space-y-1.5 text-sm text-body">
        @foreach ($package->features ?? [] as $feature)
            <p>{{ $feature }}</p>
        @endforeach
        @php $userLimit = $package->limit('users'); @endphp
        <p>{{ $userLimit ? trans_choice('billing::billing.n_users', $userLimit, ['count' => $userLimit]) : __('billing::billing.unlimited_users') }}</p>
        @foreach ($package->modules ?? [] as $module)
            <p>{{ $module }}</p>
        @endforeach
        @if ($package->trial_days > 0)
            <p class="text-brand font-medium">{{ trans_choice('billing::billing.trial_days', $package->trial_days, ['count' => $package->trial_days]) }}</p>
        @endif

        <p class="pt-4">
            <span class="font-display font-bold text-3xl text-ink">{{ $package->isFree() ? __('billing::billing.free') : Money::format($package->price) }}</span>
            <span class="text-muted">/ {{ $package->intervalLabel() }}</span>
        </p>
        @if ($package->description)
            <p class="text-muted pt-2">{{ $package->description }}</p>
        @endif
    </div>

    @if ($action || isset($footer))
        <div class="p-4 border-t border-line">
            @if ($action)
                <x-common.button :href="$action" class="w-full" :variant="$current ? 'secondary' : 'primary'">{{ $actionLabel }}</x-common.button>
            @endif
            {{ $footer ?? '' }}
        </div>
    @endif
</div>
