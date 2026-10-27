@php
    $canViewFinancialStatements = \App\Helpers\AuthHelper::can('list finance.financial_statement');
@endphp
<x-layouts.app title="{{ __('body.reports') }}">
    <x-common.page-header :title="__('body.reports')" :description="__('body.reports_description')" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('reports.bookings-summary') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.bookings_summary') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.bookings_summary_description') }}</p>
        </a>
        <a href="{{ route('reports.fleet-performance') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.fleet_performance') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.fleet_performance_description') }}</p>
        </a>
        <a href="{{ route('reports.driver-performance') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.driver_performance') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.driver_performance_description') }}</p>
        </a>
        <a href="{{ route('reports.vehicle-owner-revenue') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.vehicle_owner_revenue') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.vehicle_owner_revenue_description') }}</p>
        </a>
        <a href="{{ route('reports.compliance-status') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.compliance_status') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.compliance_status_description') }}</p>
        </a>
        <a href="{{ route('reports.customer-activity') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.customer_activity') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.customer_activity_description') }}</p>
        </a>
    </div>

    @if ($canViewFinancialStatements)
        <div class="mt-8">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">{{ __('body.accounting_reports') }}</p>
            <a href="{{ route('finance.statements.index') }}" class="block bg-white border border-line rounded-xl p-5 hover:border-brand transition">
                <h3 class="font-semibold text-ink">{{ __('body.financial_statements') }}</h3>
                <p class="text-sm text-muted mt-1">{{ __('body.financial_statements_reports_hint') }}</p>
            </a>
        </div>
    @endif
</x-layouts.app>
