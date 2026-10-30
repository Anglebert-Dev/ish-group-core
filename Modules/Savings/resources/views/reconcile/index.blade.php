<x-layouts.app :title="__('savings::savings.reconcile')">
    <x-common.page-header :title="__('savings::savings.reconcile')" :description="__('savings::savings.reconcile_description')" />
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.history') }}</h2></div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($history as $rec)
                        @php $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $rec->account?->currency); @endphp
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('savings.reconcile.show', $rec) }}" class="font-medium text-ink hover:text-brand">{{ $rec->account?->name }}</a><span class="block text-xs text-muted">{{ $rec->statement_date->format('d/m/Y') }}</span></td>
                            <td class="px-5 py-3 text-right">{{ $m($rec->statement_balance) }}</td>
                            <td class="px-5 py-3 text-right font-semibold {{ $rec->difference ? 'text-danger' : 'text-success' }}">{{ $m($rec->difference) }}</td>
                            <td class="px-5 py-3"><x-common.badge :color="match ($rec->status) { 'matched' => 'success', 'open' => 'danger', default => 'warning' }">{{ __('savings::savings.status_'.$rec->status) }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-common.pagination :paginator="$history" />
        </div>
        @if (\App\Helpers\AuthHelper::can('create savings.reconciliation'))
            <form action="{{ route('savings.reconcile.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                @csrf
                <x-common.select name="account" :label="__('savings::savings.account')" :options="$accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name.' ('.$a->currency.')'])->all()" hide-placeholder required />
                <x-common.input name="statement_date" type="date" :label="__('savings::savings.statement_date')" :value="now()->toDateString()" required />
                <x-common.input name="statement_balance" :label="__('savings::savings.statement_balance')" required />
                <x-common.button type="submit" size="sm">{{ __('savings::savings.check') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
