@if (! \Modules\InjongePay\Gtbank\GtbankClient::isConfigured())
    <div class="rounded-xl border border-warning/40 bg-warning/10 p-3 mb-4 text-sm text-ink">
        <span class="font-semibold">{{ __('injongepay::injongepay.test_mode_title') }}</span> {{ __('injongepay::injongepay.test_mode_banner') }}
    </div>
@endif
