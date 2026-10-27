@php use Modules\Billing\Support\Money; @endphp
{{-- Rendered on the superadmin business page (superadmin_business_panel hook). --}}
<div class="bg-white border border-line rounded-xl p-5">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
        <div class="space-y-1.5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.subscription') }}</h2>
            @if ($isPlatformOwner)
                <p class="text-sm text-muted">{{ __('billing::billing.platform_owner_short') }}</p>
            @elseif ($subscription)
                <div class="flex items-center gap-2">
                    <span class="font-medium text-ink">{{ $subscription->name() }}</span>
                    <x-common.badge :color="$subscription->status->color()">{{ $subscription->status->label() }}</x-common.badge>
                </div>
                <p class="text-sm text-muted">{{ Money::format($subscription->price()) }} / {{ $subscription->interval()->label($subscription->intervalCount()) }}
                    @if ($subscription->current_period_end) · {{ __('billing::billing.paid_until') }} {{ $subscription->current_period_end->format('d M Y') }} @endif</p>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    @forelse ($subscription->snapshot['modules'] ?? [] as $module)
                        <x-common.badge :color="$subscription->status->grantsAccess() ? 'success' : 'muted'">{{ $module }}</x-common.badge>
                    @empty
                        <span class="text-sm text-muted">{{ __('billing::billing.no_modules_in_package') }}</span>
                    @endforelse
                </div>
                <x-common.action-button :href="route('superadmin.billing.subscriptions.show', $subscription)">{{ __('body.manage') }}</x-common.action-button>
            @else
                <p class="text-sm text-muted">{{ __('billing::billing.no_subscription') }}</p>
            @endif
        </div>

        @unless ($isPlatformOwner)
            <form action="{{ route('superadmin.billing.subscriptions.assign', $business) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-2 w-full lg:w-auto">
                @csrf
                <x-common.select name="package_id" :label="__('billing::billing.assign_package')" :options="$packages->pluck('name', 'id')" compact required />
                <x-common.input name="coupon" :label="__('billing::billing.coupon_code')" compact />
                <x-common.button type="submit" size="sm">{{ __('billing::billing.assign') }}</x-common.button>
            </form>
        @endunless
    </div>
</div>
