@php use App\Libraries\GeneralLibrary; @endphp
<x-common.table :headers="array_filter([__('injongepayconnect::connect.when'), $names !== null ? __('body.business') : null, __('injongepayconnect::connect.request'), 'HTTP', 'ms', ''])">
    @forelse ($calls as $call)
        <tr class="hover:bg-surface">
            <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($call->created_at, 'd M Y H:i:s') }}</td>
            @if ($names !== null)<td class="px-5 py-2">{{ $names[$call->business_id] ?? $call->caller ?? '—' }}</td>@endif
            <td class="px-5 py-2 font-mono text-xs">{{ $call->method }} /{{ $call->path }}</td>
            <td class="px-5 py-2"><x-common.badge :color="$call->status < 400 ? 'success' : ($call->status < 500 ? 'warning' : 'danger')">{{ $call->status }}</x-common.badge></td>
            <td class="px-5 py-2 text-muted">{{ $call->duration_ms }}</td>
            <td class="px-5 py-2 text-right"><a class="text-brand font-semibold" href="{{ route('injongepayconnect.platform.calls.show', $call) }}">{{ __('body.view') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_calls') }}</td></tr>
    @endforelse
</x-common.table>
<x-common.pagination :paginator="$calls" class="mt-4" />
