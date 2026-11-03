@php use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepay::injongepay.businesses')">
    <x-common.page-header :title="__('injongepay::injongepay.businesses')" :description="__('injongepay::injongepay.businesses_description')" />
    @include('injongepay::partials.test-mode')

    <form method="GET" class="mb-4 flex gap-2 max-w-md">
        <x-common.input name="search" :label="__('body.search')" :value="$search" :placeholder="__('injongepay::injongepay.search_business')" compact />
        <x-common.button type="submit" size="sm">{{ __('body.apply') }}</x-common.button>
    </form>

    <x-common.table :headers="[__('body.name'), __('body.business_type'), __('injongepay::injongepay.tin'), __('injongepay::injongepay.kyc'), __('injongepay::injongepay.fee'), __('injongepay::injongepay.wallets'), __('injongepay::injongepay.total_balance'), '']">
        @forelse ($businesses as $business)
            @php $profile = $profiles->get($business->id); $missing = $profile?->missingKyc(); $w = $wallets->get($business->id); @endphp
            <tr>
                <td class="px-5 py-3 font-semibold text-ink">{{ $business->name }} @unless ($business->is_active)<x-common.badge color="danger">{{ __('body.inactive') }}</x-common.badge>@endunless</td>
                <td class="px-5 py-3">{{ $business->typeLabel() }}</td>
                <td class="px-5 py-3 font-mono">{{ $profile?->tin ?? '—' }}</td>
                <td class="px-5 py-3">
                    @if (! $profile)
                        <x-common.badge color="muted">{{ __('injongepay::injongepay.kyc_not_started') }}</x-common.badge>
                    @elseif ($missing)
                        <x-common.badge color="warning">{{ __('injongepay::injongepay.kyc_missing', ['field' => $missing]) }}</x-common.badge>
                    @else
                        <x-common.badge color="success">{{ __('injongepay::injongepay.kyc_complete') }}</x-common.badge>
                    @endif
                </td>
                <td class="px-5 py-3">{{ $profile?->fee_percentage !== null ? \Modules\InjongePay\Support\Rate::display($profile->fee_percentage).'%' : '—' }}</td>
                <td class="px-5 py-3">{{ $w->count ?? 0 }}</td>
                <td class="px-5 py-3">{{ Money::format((int) ($w->balance ?? 0)) }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('injongepay.platform.businesses.edit', $business) }}" class="text-brand font-semibold">{{ __('injongepay::injongepay.edit_profile') }}</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_businesses') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$businesses" class="mt-4" />
</x-layouts.app>
