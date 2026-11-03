@php use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.settlement_accounts')">
    <x-common.page-header :title="__('injongepay::injongepay.settlement_accounts')" :description="__('injongepay::injongepay.settlement_accounts_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-4">
            @forelse ($accounts as $account)
                <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-ink">{{ $account->label() }}</span>
                        <x-common.badge :color="['approved' => 'success', 'verified' => 'brand', 'pending' => 'warning', 'disabled' => 'muted'][$account->status]">{{ __('injongepay::injongepay.account_'.$account->status) }}</x-common.badge>
                        <span class="text-sm text-muted ml-auto">{{ $names[$account->business_id] ?? '' }}</span>
                    </div>
                    <p class="text-sm"><span class="text-muted">{{ __('injongepay::injongepay.gtbank_says') }}:</span> {{ $account->verified_names ?? '—' }} {{ $account->verified_at ? '('.GeneralLibrary::formatDateTime($account->verified_at).')' : '' }}</p>
                    @if ($account->status === 'approved' && $account->usable_from?->isFuture())
                        <p class="text-sm text-warning">{{ __('injongepay::injongepay.cooling_off_until', ['when' => GeneralLibrary::formatDateTime($account->usable_from)]) }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        @if (in_array($account->status, ['pending', 'verified'], true) && AuthHelper::can('update injongepay_platform.business'))
                            <x-common.action-button :action="route('injongepay.platform.settlement-accounts.verify', $account->uuid)">{{ __('injongepay::injongepay.verify_at_bank') }}</x-common.action-button>
                        @endif
                        @if ($account->status === 'verified' && AuthHelper::can('approve injongepay_platform.business'))
                            <x-common.action-button :action="route('injongepay.platform.settlement-accounts.approve', $account->uuid)" color="success">{{ __('injongepay::injongepay.approve') }}</x-common.action-button>
                        @endif
                    </div>
                    @if ($account->status !== 'disabled' && AuthHelper::can('update injongepay_platform.business'))
                        <form action="{{ route('injongepay.platform.settlement-accounts.disable', $account->uuid) }}" method="POST" class="flex gap-2 items-end">
                            @csrf
                            <x-common.input name="reason" :id="'r-'.$account->id" :label="__('injongepay::injongepay.reason')" required compact />
                            <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepay::injongepay.disable') }}</x-common.button>
                        </form>
                    @endif
                </div>
            @empty
                <x-common.empty-state :title="__('injongepay::injongepay.no_accounts')" />
            @endforelse
            <x-common.pagination :paginator="$accounts" />
        </div>
        @if (AuthHelper::can('create injongepay_platform.business'))
            <form action="{{ route('injongepay.platform.settlement-accounts.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.account_add') }}</h2>
                <x-common.select name="business" :label="__('body.business')" :options="$businesses" :value="old('business', request('business'))" required searchable />
                <x-common.select name="kind" :label="__('injongepay::injongepay.account_kind')" :options="['bank' => __('injongepay::injongepay.kind_bank'), 'wallet' => __('injongepay::injongepay.kind_wallet')]" :value="old('kind', 'bank')" hide-placeholder required />
                <x-common.input name="account" :label="__('injongepay::injongepay.account_number')" :hint="__('injongepay::injongepay.account_number_hint')" :value="old('account')" required />
                <x-common.input name="holder_name" :label="__('injongepay::injongepay.holder_name')" :hint="__('injongepay::injongepay.holder_name_hint')" :value="old('holder_name')" required />
                <x-common.input name="bank_name" :label="__('injongepay::injongepay.bank_name')" :value="old('bank_name')" />
                <x-common.button type="submit">{{ __('injongepay::injongepay.account_add_verify') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
