@php use App\Helpers\AuthHelper; @endphp
<x-layouts.app :title="__('injongepay::injongepay.settlement_accounts')">
    <x-common.page-header :title="__('injongepay::injongepay.settlement_accounts')" :description="__('injongepay::injongepay.settlement_accounts_description')" />
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-4">
            @forelse ($accounts as $account)
                <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-ink">{{ $account->label() }}</span>
                        <x-common.badge :color="$account->status === 'approved' ? 'success' : 'warning'">{{ __('injongepay::injongepay.account_'.$account->status) }}</x-common.badge>
                    </div>
                    <p class="text-sm"><span class="text-muted">{{ __('injongepay::injongepay.gtbank_says') }}:</span> {{ $account->verified_names ?? '—' }}</p>
                    <div class="flex flex-wrap gap-2 items-end">
                        @if ($account->status === 'pending' && AuthHelper::can('update injongepay.settlement_account'))
                            <x-common.action-button :action="route('injongepay.settlement-accounts.verify', $account)">{{ __('injongepay::injongepay.verify_at_bank') }}</x-common.action-button>
                        @endif
                        @if (AuthHelper::can('delete injongepay.settlement_account'))
                            <form action="{{ route('injongepay.settlement-accounts.destroy', $account) }}" method="POST" class="flex gap-2 items-end">
                                @csrf @method('DELETE')
                                <x-common.input name="pin" :id="'pin-'.$account->id" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required compact />
                                <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepay::injongepay.remove') }}</x-common.button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <x-common.empty-state :title="__('injongepay::injongepay.no_accounts')" />
            @endforelse
        </div>
        @if (AuthHelper::can('create injongepay.settlement_account'))
            <form action="{{ route('injongepay.settlement-accounts.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.account_add') }}</h2>
                <x-common.select name="kind" :label="__('injongepay::injongepay.account_kind')" :options="['bank' => __('injongepay::injongepay.kind_bank'), 'wallet' => __('injongepay::injongepay.kind_wallet')]" :value="old('kind', 'bank')" hide-placeholder required />
                <x-common.input name="account" :label="__('injongepay::injongepay.account_number')" :hint="__('injongepay::injongepay.account_number_hint')" :value="old('account')" required />
                <x-common.input name="holder_name" :label="__('injongepay::injongepay.holder_name')" :hint="__('injongepay::injongepay.holder_name_hint')" :value="old('holder_name')" required />
                <x-common.input name="bank_name" :label="__('injongepay::injongepay.bank_name')" :value="old('bank_name')" />
                <x-common.input name="pin" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required />
                <x-common.button type="submit">{{ __('injongepay::injongepay.account_add_verify') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
