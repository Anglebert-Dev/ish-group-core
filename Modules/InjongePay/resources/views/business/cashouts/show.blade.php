@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$cashout->reference">
    <x-common.page-header :title="Money::format($cashout->amount)" :description="$cashout->reference">
        <x-slot:actions>
            @if (! $cashout->isFinal() && \Modules\InjongePay\Gtbank\GtbankClient::isConfigured())
                <x-common.action-button :action="route('injongepay.cashouts.check', $cashout->uuid)">{{ __('injongepay::injongepay.check_now') }}</x-common.action-button>
            @endif
            <x-common.button href="{{ route('injongepay.cashouts.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <div class="space-y-4">
            <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                @include('injongepay::partials.cashout-status', ['cashout' => $cashout])
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.cashout_explain_'.$cashout->status) }}</p>
            </div>
            <x-common.info-card :rows="[
                [__('injongepay::injongepay.wallet'), $cashout->wallet?->label()],
                [__('injongepay::injongepay.to'), $cashout->account_holder.' · '.$cashout->bank_name.' '.$cashout->account],
                [__('injongepay::injongepay.asked'), GeneralLibrary::formatDateTime($cashout->created_at)],
                [__('injongepay::injongepay.bank_receipt'), $cashout->cbs_reference ?? '—'],
                [__('injongepay::injongepay.paid_at'), $cashout->completed_at ? GeneralLibrary::formatDateTime($cashout->completed_at) : '—'],
            ]" />
        </div>
        @include('injongepay::partials.cashout-events', ['cashout' => $cashout])
    </div>
</x-layouts.app>
