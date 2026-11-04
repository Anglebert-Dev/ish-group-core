@if (! \Modules\InjongePay\Gtbank\GtbankClient::isConfigured())
    <div class="rounded-xl border border-warning/40 bg-warning/10 p-3 mb-4 text-sm text-ink">
        <span class="font-semibold">{{ __('injongepay::injongepay.test_mode_title') }}</span> {{ __('injongepay::injongepay.test_mode_banner') }}
    </div>
@endif
@php
    $jobsUser = auth('staff')->user();
    $showJobs = $jobsUser && app(\App\Support\Access\PlatformAccess::class)->isOperator($jobsUser) && \App\Helpers\AuthHelper::can('update injongepay_platform.setting');
@endphp
@if ($showJobs && (\App\Support\Health\BackgroundJobs::problems() || app(\Modules\InjongePay\Services\BackgroundJobs::class)->needingAttention() > 0))
    <div class="rounded-xl border border-danger/40 bg-danger/10 p-3 mb-4 text-sm text-ink">
        <span class="font-semibold">{{ __('injongepay::injongepay.background_jobs_attention') }}</span>
        <a href="{{ route('injongepay.platform.background-jobs') }}" class="text-brand font-semibold">{{ __('injongepay::injongepay.background_jobs_open') }}</a>
    </div>
@endif
