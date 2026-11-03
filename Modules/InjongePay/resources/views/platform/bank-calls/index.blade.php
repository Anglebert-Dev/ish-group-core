<x-layouts.app :title="__('injongepay::injongepay.bank_calls')">
    <x-common.page-header :title="__('injongepay::injongepay.bank_calls')" :description="__('injongepay::injongepay.bank_calls_description')" />
    @include('injongepay::partials.test-mode')

    <x-common.filter-panel :action="route('injongepay.platform.bank-calls.index')" :open="request()->hasAny(['endpoint', 'outcome', 'direction', 'reference'])">
        <x-common.select name="endpoint" :label="__('injongepay::injongepay.endpoint')" :options="['' => '—'] + $endpoints" :value="request('endpoint')" hide-placeholder compact />
        <x-common.select name="outcome" :label="__('injongepay::injongepay.outcome')" :options="['' => '—'] + collect(['ok', 'not_found', 'rejected', 'unknown'])->mapWithKeys(fn ($o) => [$o => __('injongepay::injongepay.bank_outcome_'.$o)])->all()" :value="request('outcome')" hide-placeholder compact />
        <x-common.select name="direction" :label="__('injongepay::injongepay.direction')" :options="['' => '—', 'outbound' => __('injongepay::injongepay.direction_outbound'), 'inbound' => __('injongepay::injongepay.direction_inbound')]" :value="request('direction')" hide-placeholder compact />
        <x-common.input name="reference" :label="__('injongepay::injongepay.reference')" :value="request('reference')" compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.direction'), __('injongepay::injongepay.endpoint'), __('injongepay::injongepay.reference'), 'HTTP', __('injongepay::injongepay.outcome'), 'ms', '']">
        @forelse ($calls as $call)
            <tr>
                <td class="px-5 py-2 text-muted whitespace-nowrap">{{ \App\Libraries\GeneralLibrary::formatDateTime($call->created_at, 'd M Y H:i:s') }}</td>
                <td class="px-5 py-2">{{ __('injongepay::injongepay.direction_'.$call->direction) }}</td>
                <td class="px-5 py-2 font-mono">{{ $call->method }} {{ $call->endpoint }}</td>
                <td class="px-5 py-2 font-mono">{{ $call->reference ?? '—' }}</td>
                <td class="px-5 py-2">{{ $call->http_status ?? '—' }}</td>
                <td class="px-5 py-2">@include('injongepay::partials.outcome', ['outcome' => $call->outcome])</td>
                <td class="px-5 py-2 text-muted">{{ $call->duration_ms }}</td>
                <td class="px-5 py-2 text-right"><a class="text-brand font-semibold" href="{{ route('injongepay.platform.bank-calls.show', $call) }}">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_bank_calls') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$calls" class="mt-4" />
</x-layouts.app>
