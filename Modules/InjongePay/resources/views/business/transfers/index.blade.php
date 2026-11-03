@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.transfers')">
    <x-common.page-header :title="__('injongepay::injongepay.transfers')" :description="__('injongepay::injongepay.transfers_description')" />
    @include('injongepay::partials.test-mode')

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
            <div class="bg-card border border-line rounded-xl p-5 space-y-4">
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.transfer_new') }}</h2>
                @if (! $destination)
                    {{-- Step 1: from where, to which code, how much --}}
                    @if ($problem)
                        <div class="rounded-xl border border-danger/40 bg-danger/5 p-3 text-sm text-danger">{{ $problem }}</div>
                    @endif
                    <form method="GET" action="{{ route('injongepay.transfers.index') }}" class="space-y-4">
                        <x-common.select name="wallet" :label="__('injongepay::injongepay.from')" :options="$wallets" :value="request('wallet', old('wallet'))" required />
                        <x-common.select name="to_wallet" :label="__('injongepay::injongepay.to_account')" :options="$ownCodes" :value="request('to_wallet', request('to'))" required />
                        <x-common.input name="amount" type="number" step="1" min="1" :label="__('injongepay::injongepay.amount')" :value="request('amount', old('amount'))" required />
                        <x-common.input name="description" :label="__('injongepay::injongepay.description')" :value="request('description', old('description'))" />
                        <x-common.button type="submit">{{ __('injongepay::injongepay.transfer_check') }}</x-common.button>
                    </form>
                @else
                    {{-- Step 2: see who receives it, then confirm with the PIN --}}
                    <div class="rounded-xl border border-line bg-surface p-4 text-sm space-y-1">
                        <p class="text-muted">{{ __('injongepay::injongepay.transfer_to_confirm') }}</p>
                        <p class="text-lg font-display font-bold text-ink">{{ \Modules\InjongePay\Support\Money::format(\Modules\InjongePay\Support\Money::toCents((string) request('amount'))) }}</p>
                        <p class="font-semibold text-ink">{{ $destination->name }} · {{ $destination->merchant_code }}</p>
                        <p>{{ $destination->business?->name }}</p>
                        <p class="text-muted">{{ __('injongepay::injongepay.from') }}: {{ $from->label() }}</p>
                        <a href="{{ route('injongepay.transfers.index', request()->only('wallet', 'amount', 'description')) }}" class="text-brand font-semibold">{{ __('injongepay::injongepay.transfer_change') }}</a>
                    </div>
                    <form action="{{ route('injongepay.transfers.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="wallet" value="{{ $from->uuid }}">
                        <input type="hidden" name="merchant_code" value="{{ $destination->merchant_code }}">
                        <input type="hidden" name="amount" value="{{ request('amount') }}">
                        <input type="hidden" name="description" value="{{ request('description') }}">
                        @include('injongepay::partials.authorize')
                        <x-common.button type="submit">{{ __('injongepay::injongepay.transfer_send') }}</x-common.button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
