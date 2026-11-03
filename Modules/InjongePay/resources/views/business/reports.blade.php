<x-layouts.app :title="__('injongepay::injongepay.reports')">
    <x-common.page-header :title="__('injongepay::injongepay.reports')" :description="__('injongepay::injongepay.reports_description')">
        <x-slot:actions>
            <x-common.action-button :href="route('injongepay.reports.export', ['format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString()])">PDF</x-common.action-button>
            <x-common.action-button :href="route('injongepay.reports.export', ['format' => 'excel', 'from' => $from->toDateString(), 'to' => $to->toDateString()])">Excel</x-common.action-button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.period')
    @include('injongepay::partials.tiles')
</x-layouts.app>
