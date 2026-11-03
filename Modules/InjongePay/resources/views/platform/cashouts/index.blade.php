@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.platform_cashouts')">
    <x-common.page-header :title="__('injongepay::injongepay.platform_cashouts')" :description="__('injongepay::injongepay.platform_cashouts_description')" />
    @include('injongepay::partials.test-mode')

    <nav class="flex flex-wrap gap-2 mb-4">
        @foreach (['' => __('injongepay::injongepay.all'), 'NEEDS_REVIEW' => __('injongepay::injongepay.cashout_NEEDS_REVIEW').($review ? " ({$review})" : ''), 'open' => __('injongepay::injongepay.open_cashouts'), 'COMPLETED' => __('injongepay::injongepay.cashout_COMPLETED'), 'FAILED' => __('injongepay::injongepay.cashout_FAILED')] as $key => $label)
            <a href="{{ route('injongepay.platform.cashouts.index', array_filter(['status' => $key])) }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ (string) $status === (string) $key ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('body.business'), __('injongepay::injongepay.to'), __('injongepay::injongepay.amount'), __('body.status'), '']">
        @forelse ($cashouts as $cashout)
            <tr>
                <td class="px-5 py-3 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($cashout->created_at) }}</td>
                <td class="px-5 py-3 font-mono">{{ $cashout->reference }}</td>
                <td class="px-5 py-3">{{ $cashout->wallet?->business?->name }}<br><span class="text-xs text-muted">{{ $cashout->wallet?->label() }}</span></td>
                <td class="px-5 py-3">{{ $cashout->account_holder }}<br><span class="text-xs text-muted">{{ $cashout->account }}</span></td>
                <td class="px-5 py-3 font-semibold">{{ Money::format($cashout->amount) }}</td>
                <td class="px-5 py-3">@include('injongepay::partials.cashout-status', ['cashout' => $cashout])</td>
                <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepay.platform.cashouts.show', $cashout->uuid) }}">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_cashouts') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$cashouts" class="mt-4" />
</x-layouts.app>
