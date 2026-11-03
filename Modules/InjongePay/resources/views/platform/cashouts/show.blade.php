@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; use App\Helpers\AuthHelper; @endphp
<x-layouts.app :title="$cashout->reference">
    <x-common.page-header :title="Money::format($cashout->amount).' · '.$cashout->reference" :description="$cashout->wallet?->business?->name.' · '.$cashout->wallet?->label()">
        <x-slot:actions>
            @if (! $cashout->isFinal() && AuthHelper::can('update injongepay_platform.cashout'))
                <x-common.action-button :action="route('injongepay.platform.cashouts.check', $cashout->uuid)">{{ __('injongepay::injongepay.check_now') }}</x-common.action-button>
            @endif
            <x-common.button href="{{ route('injongepay.platform.cashouts.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="space-y-4">
            <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                @include('injongepay::partials.cashout-status', ['cashout' => $cashout])
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.cashout_explain_'.$cashout->status) }}</p>
            </div>
            <x-common.info-card :rows="[
                [__('injongepay::injongepay.to'), $cashout->account_holder.' · '.$cashout->bank_name.' '.$cashout->account],
                [__('injongepay::injongepay.submit_outcome'), $cashout->submit_outcome ? __('injongepay::injongepay.submit_'.$cashout->submit_outcome) : '—'],
                [__('injongepay::injongepay.bank_status'), $cashout->bank_status ?? '—'],
                [__('injongepay::injongepay.webhook_status'), $cashout->webhook_status ?? '—'],
                [__('injongepay::injongepay.last_answer'), $cashout->last_answer ? __('injongepay::injongepay.answer_'.$cashout->last_answer) : '—'],
                [__('injongepay::injongepay.checks_done'), $cashout->checks.' · '.__('injongepay::injongepay.not_found_times', ['n' => $cashout->not_found_count])],
                [__('injongepay::injongepay.bank_receipt'), $cashout->cbs_reference ?? '—'],
                [__('injongepay::injongepay.receiver'), $cashout->receiver_names ?? '—'],
                [__('injongepay::injongepay.message'), $cashout->failure_reason ?? '—'],
            ]" />
            @if (! $cashout->isFinal() && AuthHelper::can('approve injongepay_platform.cashout'))
                <form action="{{ route('injongepay.platform.cashouts.resolve', $cashout->uuid) }}" method="POST" class="bg-card border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('injongepay::injongepay.settle_by_hand') }}</h2>
                    <p class="text-sm text-muted">{{ __('injongepay::injongepay.settle_by_hand_hint') }}</p>
                    <x-common.select name="outcome" :label="__('injongepay::injongepay.gtbank_confirmed')" :options="['completed' => __('injongepay::injongepay.outcome_completed'), 'failed' => __('injongepay::injongepay.outcome_failed')]" required />
                    <x-common.textarea name="note" :label="__('injongepay::injongepay.evidence')" required />
                    <x-common.button type="submit" size="sm" variant="danger">{{ __('injongepay::injongepay.settle') }}</x-common.button>
                </form>
            @endif
        </div>
        @include('injongepay::partials.cashout-events', ['cashout' => $cashout])
        <div class="bg-card border border-line rounded-xl p-5 space-y-2">
            <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.bank_calls') }}</h2>
            @foreach ($calls as $call)
                <a href="{{ route('injongepay.platform.bank-calls.show', $call) }}" class="flex items-center gap-2 text-sm border-b border-line pb-2">
                    <span class="font-mono flex-1">{{ $call->method }} {{ $call->endpoint }}</span>
                    @include('injongepay::partials.outcome', ['outcome' => $call->outcome])
                    <span class="text-xs text-muted">{{ GeneralLibrary::formatDateTime($call->created_at, 'H:i:s') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.app>
