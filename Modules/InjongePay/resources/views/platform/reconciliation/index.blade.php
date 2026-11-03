@php use Modules\InjongePay\Support\Money; use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.reconciliation')">
    <x-common.page-header :title="__('injongepay::injongepay.reconciliation')" :description="__('injongepay::injongepay.reconciliation_description')">
        <x-slot:actions>
            @if (AuthHelper::can('update injongepay_platform.bank'))
                <x-common.action-button :action="route('injongepay.platform.reconciliation.pull')">{{ __('injongepay::injongepay.pull_now') }}</x-common.action-button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')

    <div class="rounded-xl border p-4 mb-6 {{ $difference === 0 ? 'border-success/40 bg-success/5' : 'border-danger/40 bg-danger/5' }}">
        <p class="font-semibold {{ $difference === 0 ? 'text-success' : 'text-danger' }}">
            {{ $difference === 0 ? __('injongepay::injongepay.platform_balanced') : __('injongepay::injongepay.platform_unbalanced', ['amount' => Money::format($difference)]) }}
        </p>
        <p class="text-sm text-muted mt-1">{{ __('injongepay::injongepay.platform_identity') }}</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
        <div class="xl:col-span-3 space-y-4">
            <nav class="flex gap-2">
                @foreach (['open', 'resolved'] as $tab)
                    <a href="{{ route('injongepay.platform.reconciliation.index', ['status' => $tab, 'day' => $day->toDateString()]) }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ $status === $tab ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ __('injongepay::injongepay.items_'.$tab) }}</a>
                @endforeach
            </nav>
            @forelse ($items as $item)
                <div class="bg-card border border-line rounded-xl p-5 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-common.badge :color="$item->status === 'open' ? 'danger' : 'success'">{{ __('injongepay::injongepay.item_'.$item->kind) }}</x-common.badge>
                        @if ($item->amount !== null)<span class="font-semibold text-ink">{{ Money::format($item->amount) }}</span>@endif
                        <span class="text-sm text-muted">{{ $item->wallet?->label() }} {{ $item->business ? '· '.$item->business->name : '' }}</span>
                        <span class="text-xs text-muted ml-auto">{{ GeneralLibrary::formatDateTime($item->created_at) }}</span>
                    </div>
                    <p class="text-sm text-muted">{{ __('injongepay::injongepay.item_hint_'.$item->kind) }}</p>
                    @if ($item->details)<pre class="text-xs bg-surface border border-line rounded-lg p-2 overflow-x-auto">{{ json_encode($item->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                    @if ($item->status === 'open' && AuthHelper::can('approve injongepay_platform.reconciliation'))
                        <form action="{{ route('injongepay.platform.reconciliation.resolve', $item) }}" method="POST" class="flex flex-col sm:flex-row gap-2 sm:items-end">
                            @csrf
                            <x-common.input name="note" :id="'note-'.$item->id" :label="__('injongepay::injongepay.explanation')" required compact class="flex-1" />
                            <x-common.button type="submit" size="sm">{{ __('injongepay::injongepay.resolve') }}</x-common.button>
                        </form>
                    @elseif ($item->status === 'resolved')
                        <p class="text-sm"><span class="text-muted">{{ $item->resolved_by_name }}:</span> {{ $item->resolution_note }}</p>
                    @endif
                </div>
            @empty
                <x-common.empty-state :title="__('injongepay::injongepay.items_none')" />
            @endforelse
            <x-common.pagination :paginator="$items" />
        </div>

        <div class="xl:col-span-2 bg-card border border-line rounded-xl p-5 space-y-4">
            <form method="GET" action="{{ route('injongepay.platform.reconciliation.index') }}" class="flex gap-2 items-end">
                <x-common.input name="day" type="date" :label="__('injongepay::injongepay.day')" :value="$day->toDateString()" compact />
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.view') }}</x-common.button>
            </form>
            @if (AuthHelper::can('update injongepay_platform.reconciliation'))
                <x-common.action-button :action="route('injongepay.platform.reconciliation.close-day')">
                    <x-slot:fields><input type="hidden" name="day" value="{{ $day->toDateString() }}"></x-slot:fields>
                    {{ __('injongepay::injongepay.close_day', ['day' => $day->toDateString()]) }}
                </x-common.action-button>
            @endif
            <table class="w-full text-sm"><tbody class="divide-y divide-line">
                @forelse ($closes as $close)
                    <tr>
                        <td class="py-2 font-mono">{{ $close->wallet?->merchant_code }}</td>
                        <td class="py-2">GTBank {{ $close->bank_count }} · {{ Money::format($close->bank_total) }}<br><span class="text-muted">{{ __('injongepay::injongepay.ours') }} {{ $close->our_count }} · {{ Money::format($close->our_total) }}</span></td>
                        <td class="py-2 text-right"><x-common.badge :color="['reconciled' => 'success', 'differences' => 'danger', 'bank_unreachable' => 'warning'][$close->state]">{{ __('injongepay::injongepay.close_'.$close->state) }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-muted">{{ __('injongepay::injongepay.no_close') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
</x-layouts.app>
