@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.transfers')">
    <x-common.page-header :title="__('injongepay::injongepay.transfers')" :description="__('injongepay::injongepay.transfers_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2">
            <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('injongepay::injongepay.from'), __('injongepay::injongepay.to'), __('injongepay::injongepay.amount')]">
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="px-5 py-3 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($transfer->created_at) }}</td>
                        <td class="px-5 py-3 font-mono">{{ $transfer->reference }}</td>
                        <td class="px-5 py-3">{{ $transfer->fromWallet?->label() }}</td>
                        <td class="px-5 py-3">{{ $transfer->toWallet?->label() }}</td>
                        <td class="px-5 py-3 font-semibold">{{ Money::format($transfer->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_transfers') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$transfers" class="mt-4" />
        </div>
        @if (\App\Helpers\AuthHelper::can('create injongepay.transfer'))
            <form action="{{ route('injongepay.transfers.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.transfer_new') }}</h2>
                <x-common.select name="wallet" :label="__('injongepay::injongepay.from')" :options="$wallets" :value="old('wallet')" required />
                <x-common.input name="merchant_code" :label="__('injongepay::injongepay.to_merchant_code')" :value="old('merchant_code')" required inputmode="numeric" />
                <x-common.input name="amount" type="number" step="1" min="1" :label="__('injongepay::injongepay.amount')" :value="old('amount')" required />
                <x-common.input name="description" :label="__('injongepay::injongepay.description')" :value="old('description')" />
                @include('injongepay::partials.authorize')
                <x-common.button type="submit">{{ __('injongepay::injongepay.transfer_send') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
