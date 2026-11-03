@php use App\Libraries\GeneralLibrary; use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepayconnect::connect.linked_invoices')">
    <x-common.page-header :title="__('injongepayconnect::connect.business_title')" :description="__('injongepayconnect::connect.invoices_description')" />
    @include('injongepayconnect::business._tabs')

    <x-common.filter-panel :action="route('injongepayconnect.invoices')" :open="request()->hasAny(['invoice', 'status'])">
        <x-common.input name="invoice" :label="__('injongepayconnect::connect.invoice_no')" :value="$invoice" compact />
        <x-common.select name="status" :label="__('body.status')" :options="['' => __('body.all'), 'CONFIRMED' => __('injongepayconnect::connect.status_CONFIRMED'), 'UNCONFIRMED' => __('injongepayconnect::connect.status_UNCONFIRMED')]" :value="request('status')" hide-placeholder compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('injongepayconnect::connect.invoice_no'), __('injongepayconnect::connect.payment'), __('injongepay::injongepay.amount'), __('body.status'), __('injongepayconnect::connect.by')]">
        @forelse ($confirmations as $c)
            <tr>
                <td class="px-5 py-3 font-mono">{{ $c->invoice_no }} @if ($c->is_partial)<x-common.badge color="muted">{{ __('injongepayconnect::connect.partial') }}</x-common.badge>@endif</td>
                <td class="px-5 py-3">@if ($c->payment)<a class="hover:text-brand" href="{{ route('injongepay.payments.show', $c->payment) }}">{{ $c->payment->buyer_name ?? $c->payment->bank_reference }}</a>@else — @endif</td>
                <td class="px-5 py-3">{{ $c->payment ? Money::format($c->payment->gross_amount) : '—' }}</td>
                <td class="px-5 py-3"><x-common.badge :color="$c->status === 'CONFIRMED' ? 'success' : 'muted'">{{ __('injongepayconnect::connect.status_'.$c->status) }}</x-common.badge>@if ($c->unconfirm_reason)<br><span class="text-xs text-muted">{{ $c->unconfirm_reason }}</span>@endif</td>
                <td class="px-5 py-3 text-muted">{{ $c->confirmed_by_name ?? '—' }} · {{ GeneralLibrary::formatDateTime($c->confirmed_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_confirmations') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$confirmations" class="mt-4" />
</x-layouts.app>
