@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.cashouts')">
    <x-common.page-header :title="__('injongepay::injongepay.cashouts')" :description="__('injongepay::injongepay.cashouts_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2">
            <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('injongepay::injongepay.wallet'), __('injongepay::injongepay.to'), __('injongepay::injongepay.amount'), __('body.status'), '']">
                @forelse ($cashouts as $cashout)
                    <tr>
                        <td class="px-5 py-3 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($cashout->created_at) }}</td>
                        <td class="px-5 py-3 font-mono">{{ $cashout->reference }}</td>
                        <td class="px-5 py-3">{{ $cashout->wallet?->name }}</td>
                        <td class="px-5 py-3">{{ $cashout->account_holder }}<br><span class="text-xs text-muted">{{ $cashout->bank_name }} {{ $cashout->account }}</span></td>
                        <td class="px-5 py-3 font-semibold">{{ Money::format($cashout->amount) }}</td>
                        <td class="px-5 py-3">@include('injongepay::partials.cashout-status', ['cashout' => $cashout])</td>
                        <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepay.cashouts.show', $cashout) }}">{{ __('body.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_cashouts') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$cashouts" class="mt-4" />
        </div>
        @if (\App\Helpers\AuthHelper::can('create injongepay.cashout'))
            <form action="{{ route('injongepay.cashouts.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.cashout_new') }}</h2>
                @if ($accounts === [])
                    <p class="text-sm text-muted">{{ __('injongepay::injongepay.no_usable_accounts') }}
                        @if (\App\Helpers\AuthHelper::can('list injongepay.settlement_account'))<a class="text-brand font-semibold" href="{{ route('injongepay.settlement-accounts.index') }}">{{ __('injongepay::injongepay.settlement_accounts') }}</a>@endif</p>
                @else
                    <x-common.select name="wallet" :label="__('injongepay::injongepay.from')" :options="$wallets" :value="old('wallet')" required />
                    <x-common.select name="account" :label="__('injongepay::injongepay.to')" :options="$accounts" :value="old('account')" required />
                    <x-common.input name="amount" type="number" step="1" min="1" :label="__('injongepay::injongepay.amount')" :hint="__('injongepay::injongepay.whole_rwf_hint')" :value="old('amount')" required />
                    @include('injongepay::partials.authorize')
                    <x-common.button type="submit">{{ __('injongepay::injongepay.cashout_send') }}</x-common.button>
                @endif
            </form>
        @endif
    </div>
</x-layouts.app>
