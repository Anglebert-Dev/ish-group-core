@php use Modules\InjongePay\Support\Money; use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.unallocated')">
    <x-common.page-header :title="__('injongepay::injongepay.unallocated')" :description="__('injongepay::injongepay.unallocated_description', ['total' => Money::format($openTotal)])">
        <x-slot:actions>
            @if (AuthHelper::can('update injongepay_platform.bank'))
                <x-common.action-button :action="route('injongepay.platform.inbox.process')">{{ __('injongepay::injongepay.process_inbox') }}</x-common.action-button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <nav class="flex gap-2 mb-4">
        @foreach (['open', 'allocated', 'settled_outside'] as $tab)
            <a href="{{ route('injongepay.platform.unallocated.index', ['status' => $tab]) }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ $status === $tab ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ __('injongepay::injongepay.unallocated_'.$tab) }}</a>
        @endforeach
    </nav>

    <div class="space-y-4">
        @forelse ($rows as $row)
            <div class="bg-card border border-line rounded-xl p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="space-y-1 text-sm">
                    <p class="text-xl font-display font-bold text-ink">{{ Money::format($row->amount) }}</p>
                    <p><span class="text-muted">{{ __('injongepay::injongepay.merchant_code') }}:</span> <span class="font-mono">{{ $row->merchant_code ?? '—' }}</span> {{ $row->business ? '· '.$row->business->name : '' }}</p>
                    <p><span class="text-muted">GTBank:</span> <span class="font-mono">{{ $row->bank_reference }}</span></p>
                    <p><span class="text-muted">{{ __('injongepay::injongepay.payer') }}:</span> {{ $row->payload['debtorName'] ?? '—' }} {{ $row->payload['debtorAccountId'] ?? '' }}</p>
                    <p class="text-muted">{{ GeneralLibrary::formatDateTime($row->created_at) }}</p>
                    <x-common.badge color="warning">{{ __('injongepay::injongepay.park_reason_'.$row->reason) }}</x-common.badge>
                </div>
                @if ($row->status === 'open' && AuthHelper::can('approve injongepay_platform.reconciliation'))
                    <form action="{{ route('injongepay.platform.unallocated.allocate', $row) }}" method="POST" class="space-y-3">
                        @csrf
                        <x-common.select name="wallet" :id="'w-'.$row->id" :label="__('injongepay::injongepay.credit_to')" :options="$wallets" :value="$row->wallet?->uuid" required searchable compact />
                        <x-common.input name="note" :id="'n-'.$row->id" :label="__('injongepay::injongepay.reason')" required compact />
                        <x-common.button type="submit" size="sm">{{ __('injongepay::injongepay.allocate') }}</x-common.button>
                    </form>
                    <form action="{{ route('injongepay.platform.unallocated.settle-outside', $row) }}" method="POST" class="space-y-3">
                        @csrf
                        <p class="text-sm text-muted">{{ __('injongepay::injongepay.settle_outside_hint') }}</p>
                        <x-common.input name="note" :id="'s-'.$row->id" :label="__('injongepay::injongepay.how_settled')" required compact />
                        <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepay::injongepay.settle_outside') }}</x-common.button>
                    </form>
                @elseif ($row->status !== 'open')
                    <div class="lg:col-span-2 text-sm space-y-1">
                        <p><span class="text-muted">{{ __('injongepay::injongepay.resolved_by') }}:</span> {{ $row->resolved_by_name }} · {{ GeneralLibrary::formatDateTime($row->resolved_at) }}</p>
                        <p>{{ $row->resolution_note }}</p>
                    </div>
                @endif
            </div>
        @empty
            <x-common.empty-state :title="__('injongepay::injongepay.unallocated_none')" />
        @endforelse
    </div>
    <x-common.pagination :paginator="$rows" class="mt-4" />
</x-layouts.app>
