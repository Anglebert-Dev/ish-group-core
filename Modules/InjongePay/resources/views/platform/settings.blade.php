<x-layouts.app :title="__('injongepay::injongepay.platform_settings')">
    <x-common.page-header :title="__('injongepay::injongepay.platform_settings')" :description="__('injongepay::injongepay.platform_settings_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <div class="space-y-6">
            <form action="{{ route('injongepay.platform.settings.fees') }}" method="POST" class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4">
                @csrf @method('PUT')
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.fees') }}</h2>
                @php $canFees = \App\Helpers\AuthHelper::can('update injongepay_platform.fee'); @endphp
                <fieldset @disabled(! $canFees) class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-common.input name="fee_percentage" type="number" step="0.01" min="0" max="100" :label="__('injongepay::injongepay.fee_percentage')" :value="old('fee_percentage', $fee)" required />
                    <x-common.input name="bank_cut_percentage" type="number" step="0.01" min="0" max="100" :label="__('injongepay::injongepay.bank_cut_percentage')" :hint="__('injongepay::injongepay.bank_cut_hint')" :value="old('bank_cut_percentage', $bankCut)" required />
                </fieldset>
                @if ($canFees)
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                @endif
            </form>

            <div class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4">
                <div>
                    <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.webhooks') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('injongepay::injongepay.webhooks_description') }}</p>
                </div>
                <x-common.info-card :rows="collect($webhookUrls)->map(fn ($url, $label) => [$label, $url])->values()->all()" />
                <p class="text-sm {{ $hasSecret ? 'text-body' : 'text-danger font-semibold' }}">{{ $hasSecret ? __('injongepay::injongepay.secret_set') : __('injongepay::injongepay.secret_none') }}</p>
                @if ($newSecret)
                    <div class="rounded-xl border border-warning/40 bg-warning/10 p-4 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.webhook_secret') }}</p>
                        <p class="font-mono text-ink break-all select-all">{{ $newSecret }}</p>
                        <p class="text-xs text-muted">{{ __('injongepay::injongepay.secret_shown_once') }}</p>
                    </div>
                @endif
                @if (\App\Helpers\AuthHelper::can('update injongepay_platform.setting'))
                    <x-common.action-button :action="route('injongepay.platform.settings.secret')" color="warning">{{ __('injongepay::injongepay.secret_generate') }}</x-common.action-button>
                @endif
                <p class="text-sm {{ $bankConfigured ? 'text-success' : 'text-warning' }}">{{ $bankConfigured ? __('injongepay::injongepay.bank_configured') : __('injongepay::injongepay.bank_not_configured') }}</p>
            </div>
        </div>

        <div class="bg-card border border-line rounded-xl p-5 space-y-3">
            <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.fee_history') }}</h2>
            @forelse ($history as $change)
                <div class="text-sm border-b border-line pb-2">
                    <p class="text-ink font-semibold">{{ __('injongepay::injongepay.scope_'.$change->scope) }}: {{ $change->old_value ?? '—' }}% → {{ $change->new_value ?? '—' }}%</p>
                    <p class="text-muted text-xs">{{ \App\Libraries\GeneralLibrary::formatDateTime($change->created_at) }} · {{ $change->changed_by_name ?? '—' }}</p>
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.no_fee_history') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
