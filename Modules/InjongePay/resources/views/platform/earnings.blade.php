@php use Modules\InjongePay\Support\Money; use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.earnings')">
    <x-common.page-header :title="__('injongepay::injongepay.earnings')" :description="__('injongepay::injongepay.earnings_description')" />
    @include('injongepay::partials.test-mode')

    @unless ($hasPin)
        <div class="rounded-xl border border-warning/40 bg-warning/5 p-4 mb-6 text-sm">
            {{ __('injongepay::injongepay.set_pin_first') }} <a href="{{ route('injongepay.security') }}" class="text-brand font-semibold">{{ __('injongepay::injongepay.pin_set') }}</a>
        </div>
    @endunless
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        @foreach ($wallets as $wallet)
            <div class="bg-card border border-line rounded-xl p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $wallet->name }}</p>
                <p class="text-2xl font-display font-bold text-ink mt-1">{{ Money::format($wallet->available_balance) }}</p>
                <p class="text-sm text-muted mt-1">{{ __('injongepay::injongepay.earnings_hint_'.$wallet->internal_role) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div>
                <h2 class="font-display font-bold text-ink mb-3">{{ __('injongepay::injongepay.cashouts') }}</h2>
                <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('injongepay::injongepay.from'), __('injongepay::injongepay.to'), __('injongepay::injongepay.amount'), __('body.status'), '']">
                    @forelse ($cashouts as $cashout)
                        <tr>
                            <td class="px-5 py-3 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($cashout->created_at) }}</td>
                            <td class="px-5 py-3 font-mono">{{ $cashout->reference }}</td>
                            <td class="px-5 py-3">{{ $wallets->firstWhere('id', $cashout->wallet_id)?->name }}</td>
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
            </div>

            <div>
                <h2 class="font-display font-bold text-ink mb-3">{{ __('injongepay::injongepay.inhills_accounts') }}</h2>
                <x-common.table :headers="[__('injongepay::injongepay.account_number'), __('injongepay::injongepay.gtbank_says'), __('body.status'), '']">
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="px-5 py-3">{{ $account->label() }}</td>
                            <td class="px-5 py-3">{{ $account->verified_names ?? '—' }}</td>
                            <td class="px-5 py-3"><x-common.badge :color="$account->status === 'approved' ? 'success' : 'warning'">{{ __('injongepay::injongepay.account_'.$account->status) }}</x-common.badge></td>
                            <td class="px-5 py-3 text-right">
                                @if (AuthHelper::can('delete injongepay_platform.earnings'))
                                    <form action="{{ route('injongepay.platform.earnings.accounts.destroy', $account) }}" method="POST" class="inline-flex gap-2 items-end">
                                        @csrf @method('DELETE')
                                        <x-common.input name="pin" :id="'pin-'.$account->id" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required compact />
                                        <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepay::injongepay.remove') }}</x-common.button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_accounts') }}</td></tr>
                    @endforelse
                </x-common.table>
            </div>
        </div>

        <div class="space-y-6">
            @if (AuthHelper::can('create injongepay_platform.earnings'))
                <form action="{{ route('injongepay.platform.earnings.cashout') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.cashout_new') }}</h2>
                    @if ($accounts->where('status', 'approved')->isEmpty())
                        <p class="text-sm text-muted">{{ __('injongepay::injongepay.no_usable_accounts') }}</p>
                    @else
                        @php $first = array_key_first($walletOptions); @endphp
                        <x-common.select name="wallet" :label="__('injongepay::injongepay.from')" :options="$walletOptions" :value="old('wallet', $first)" required />
                        <x-common.select name="account" :label="__('injongepay::injongepay.to')" :options="$accounts->where('status', 'approved')->mapWithKeys(fn ($a) => [$a->uuid => $a->label()])->all()" :value="old('account')" required />
                        <x-common.input name="amount" type="number" step="1" min="1" :label="__('injongepay::injongepay.amount')" :hint="__('injongepay::injongepay.whole_rwf_hint')" :value="old('amount', $available[$first] ?? null)" required />
                        @include('injongepay::partials.authorize')
                        <x-common.button type="submit">{{ __('injongepay::injongepay.cashout_send') }}</x-common.button>
                    @endif
                </form>
                <script>
                    (function () {
                        var available = @json($available);
                        var wallet = document.getElementById('field-wallet');
                        var amount = document.getElementById('field-amount');
                        if (wallet && amount) wallet.addEventListener('change', function () { if (available[wallet.value] !== undefined) amount.value = available[wallet.value]; });
                    })();
                </script>

                <form action="{{ route('injongepay.platform.earnings.accounts.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.account_add') }}</h2>
                    <x-common.select name="kind" :label="__('injongepay::injongepay.account_kind')" :options="['bank' => __('injongepay::injongepay.kind_bank'), 'wallet' => __('injongepay::injongepay.kind_wallet')]" :value="old('kind', 'bank')" hide-placeholder required />
                    <x-common.input name="account" :id="'new-account'" :label="__('injongepay::injongepay.account_number')" :hint="__('injongepay::injongepay.account_number_hint')" :value="old('account')" required />
                    <x-common.input name="holder_name" :label="__('injongepay::injongepay.holder_name')" :hint="__('injongepay::injongepay.holder_name_hint')" :value="old('holder_name')" required />
                    <x-common.input name="bank_name" :label="__('injongepay::injongepay.bank_name')" :value="old('bank_name')" />
                    <x-common.input name="pin" :id="'new-account-pin'" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required />
                    <x-common.button type="submit" size="sm">{{ __('injongepay::injongepay.account_add_verify') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
