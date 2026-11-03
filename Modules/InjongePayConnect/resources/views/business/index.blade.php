@php use App\Libraries\GeneralLibrary; use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepayconnect::connect.business_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.business_title')" :description="__('injongepayconnect::connect.business_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-4">
            <form method="GET" class="flex gap-2 items-end max-w-md">
                <x-common.input name="invoice" :label="__('injongepayconnect::connect.invoice_no')" :value="$invoice" compact />
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.search') }}</x-common.button>
            </form>
            <x-common.table :headers="[__('injongepayconnect::connect.invoice_no'), __('injongepayconnect::connect.payment'), __('injongepay::injongepay.amount'), __('body.status'), __('injongepayconnect::connect.by')]">
                @forelse ($confirmations as $c)
                    <tr>
                        <td class="px-5 py-3 font-mono">{{ $c->invoice_no }} @if ($c->is_partial)<x-common.badge color="muted">{{ __('injongepayconnect::connect.partial') }}</x-common.badge>@endif</td>
                        <td class="px-5 py-3">{{ $c->payment?->buyer_name }} <span class="text-xs text-muted">{{ $c->payment?->bank_reference }}</span></td>
                        <td class="px-5 py-3">{{ $c->payment ? Money::format($c->payment->gross_amount) : '—' }}</td>
                        <td class="px-5 py-3"><x-common.badge :color="$c->status === 'CONFIRMED' ? 'success' : 'muted'">{{ __('injongepayconnect::connect.status_'.$c->status) }}</x-common.badge>@if ($c->unconfirm_reason)<br><span class="text-xs text-muted">{{ $c->unconfirm_reason }}</span>@endif</td>
                        <td class="px-5 py-3 text-muted">{{ $c->confirmed_by_name ?? '—' }} · {{ GeneralLibrary::formatDateTime($c->confirmed_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_confirmations') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$confirmations" />
        </div>
        <div class="space-y-4">
            <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                <h2 class="font-display font-bold text-ink">{{ __('injongepayconnect::connect.connected_systems') }}</h2>
                @forelse ($connections as $connection)
                    <p class="text-sm"><span class="font-semibold text-ink">{{ $connection->integrator?->name }}</span> · <span class="font-mono">{{ $connection->client_id }}</span>
                        <x-common.badge :color="$connection->is_enabled ? 'success' : 'muted'">{{ $connection->is_enabled ? __('injongepayconnect::connect.on') : __('injongepayconnect::connect.off') }}</x-common.badge></p>
                @empty
                    <p class="text-sm text-muted">{{ __('injongepayconnect::connect.none_yet') }}</p>
                @endforelse
            </div>
            <div class="bg-card border border-line rounded-xl p-5 space-y-1 text-sm">
                <h2 class="font-display font-bold text-ink mb-2">{{ __('injongepayconnect::connect.calls') }}</h2>
                @foreach ($calls as $call)
                    <p><span class="text-muted">{{ GeneralLibrary::formatDateTime($call->created_at, 'd M H:i') }}</span> <span class="font-mono">{{ $call->method }} /{{ $call->path }}</span> · {{ $call->status }}</p>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
