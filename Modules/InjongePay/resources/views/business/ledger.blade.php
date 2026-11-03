@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.ledger').' · '.$wallet->label()">
    <x-common.page-header :title="__('injongepay::injongepay.ledger')" :description="$wallet->label()" />
    <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.reference'), __('injongepay::injongepay.description'), __('injongepay::injongepay.amount'), __('injongepay::injongepay.available'), __('injongepay::injongepay.pending')]">
        @forelse ($entries as $entry)
            <tr>
                <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($entry->created_at) }}</td>
                <td class="px-5 py-2 font-mono">{{ $entry->reference }}</td>
                <td class="px-5 py-2">{{ __('injongepay::injongepay.source_'.$entry->source) }}<br><span class="text-xs text-muted">{{ $entry->description }} · {{ __('injongepay::injongepay.'.$entry->bucket) }}</span></td>
                <td class="px-5 py-2 {{ $entry->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $entry->direction === 'credit' ? '+' : '−' }}{{ Money::format($entry->amount) }}</td>
                <td class="px-5 py-2">{{ Money::format($entry->available_after) }}</td>
                <td class="px-5 py-2 text-muted">{{ Money::format($entry->pending_after) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_entries') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$entries" class="mt-4" />
</x-layouts.app>
