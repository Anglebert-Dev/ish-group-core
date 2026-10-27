<x-layouts.app :title="__('billing::billing.coupons')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.coupons')" :description="__('billing::billing.coupons_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.billing.coupons.create') }}" size="sm">+ {{ __('billing::billing.add_coupon') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.code') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.discount') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.used') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.expires') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($coupons as $coupon)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-mono font-semibold text-ink">{{ $coupon->code }}</td>
                        <td class="px-5 py-3.5">{{ $coupon->summary() }}</td>
                        <td class="px-5 py-3.5">{{ $coupon->redemptions_count }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $coupon->expires_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$coupon->is_active ? 'success' : 'muted'">{{ $coupon->is_active ? __('body.active') : __('body.inactive') }}</x-common.badge></td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.action-button :href="route('superadmin.billing.coupons.edit', $coupon)">{{ __('body.edit') }}</x-common.action-button>
                            <x-common.action-button :action="route('superadmin.billing.coupons.destroy', $coupon)" method="DELETE" color="danger"
                                data-confirm data-confirm-title="{{ __('body.delete') }} {{ $coupon->code }}?" data-confirm-variant="danger">{{ __('body.delete') }}</x-common.action-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('billing::billing.no_coupons') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$coupons" class="border-t border-line" />
    </div>
</x-layouts.app>
