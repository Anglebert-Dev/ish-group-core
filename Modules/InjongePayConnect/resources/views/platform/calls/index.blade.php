<x-layouts.app :title="__('injongepayconnect::connect.platform_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.platform_title')" :description="__('injongepayconnect::connect.calls_description')" />
    @include('injongepayconnect::platform._tabs')

    <x-common.filter-panel :action="route('injongepayconnect.platform.calls.index')" :open="request()->hasAny(['business', 'result', 'path'])">
        <x-common.select name="business" :label="__('body.business')" :options="['' => __('body.all')] + $businesses" :value="request('business')" hide-placeholder searchable compact />
        <x-common.select name="result" :label="__('injongepayconnect::connect.result')" :options="['' => __('body.all'), 'ok' => __('injongepayconnect::connect.result_ok'), 'errors' => __('injongepayconnect::connect.result_errors')]" :value="request('result')" hide-placeholder compact />
        <x-common.input name="path" :label="__('injongepayconnect::connect.request')" :value="request('path')" placeholder="payments" compact />
    </x-common.filter-panel>

    @include('injongepayconnect::platform.calls._table', ['calls' => $calls, 'names' => $names])
</x-layouts.app>
