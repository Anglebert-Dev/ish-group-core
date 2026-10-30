@php $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $account->currency); @endphp
<x-layouts.app :title="$account->name">
    <x-common.page-header :title="$account->name" :description="__('savings::savings.account_'.$account->type).' · '.$account->currency">
        <x-slot:actions>
            <x-common.button href="{{ route('savings.accounts.index') }}" variant="secondary" size="sm">← {{ __('savings::savings.accounts') }}</x-common.button>
            <x-common.button href="{{ route('savings.forecast', ['account' => $account->uuid]) }}" variant="secondary" size="sm">{{ __('savings::savings.forecast') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        @foreach (['balance' => $balance, 'committed' => $available['commitments'], 'earmarked' => $available['earmarks'], 'kept_minimum' => $available['minimum'], 'available' => $available['available']] as $label => $value)
            <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs font-semibold uppercase text-muted">{{ __('savings::savings.'.$label) }}</p><p class="text-lg font-display font-bold mt-1 {{ $value < 0 ? 'text-danger' : 'text-ink' }}">{{ $m($value) }}</p></div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.ledger') }}</h2></div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($entries as $entry)
                        <tr class="{{ $entry->transaction?->isReversed() || $entry->transaction?->isReversal() ? 'opacity-60' : '' }}">
                            <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $entry->effective_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3"><a href="{{ route('savings.transactions.show', $entry->transaction) }}" class="text-ink hover:text-brand">{{ $entry->transaction?->description }}</a>
                                <span class="block text-xs text-muted">{{ __('savings::savings.type_'.$entry->transaction?->type) }}</span></td>
                            <td class="px-5 py-3 text-right">@include('savings::partials.money', ['v' => $entry->amount, 'c' => $account->currency, 'signed' => true])</td>
                            <td class="px-5 py-3 text-right text-muted">{{ $m($after[$entry->id]) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
                    @endforelse
                    <tr class="bg-surface/60"><td class="px-5 py-3 text-muted">{{ $account->opening_date->format('d/m/Y') }}</td><td class="px-5 py-3" colspan="2">{{ __('savings::savings.opening_balance') }}</td><td class="px-5 py-3 text-right">{{ $m($account->opening_balance) }}</td></tr>
                </tbody>
            </table>
            <x-common.pagination :paginator="$entries" />
        </div>

        <div class="space-y-4">
            @if (\App\Helpers\AuthHelper::can('update savings.account'))
                <details class="bg-white border border-line rounded-xl p-5">
                    <summary class="cursor-pointer font-display font-bold text-ink">{{ __('savings::savings.account_edit') }}</summary>
                    <form action="{{ route('savings.accounts.update', $account) }}" method="POST" class="mt-4 space-y-4">
                        @csrf @method('PUT')
                        @include('savings::accounts.form-fields', ['account' => $account, 'prefix' => 'edit-'])
                        <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                    </form>
                </details>
                <x-common.action-button :action="route('savings.accounts.archive', $account)" method="PUT" :color="$account->isArchived() ? 'success' : 'warning'">{{ $account->isArchived() ? __('savings::savings.unarchive') : __('savings::savings.archive') }}</x-common.action-button>
            @endif
            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <div class="flex justify-between"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.reconcile') }}</h2><a href="{{ route('savings.reconcile.index') }}" class="text-sm font-semibold text-brand">→</a></div>
                @forelse ($reconciliations as $rec)
                    <a href="{{ route('savings.reconcile.show', $rec) }}" class="flex justify-between text-sm"><span>{{ $rec->statement_date->format('d/m/Y') }}</span><span class="{{ $rec->difference ? 'text-danger' : 'text-success' }}">{{ __('savings::savings.status_'.$rec->status) }} {{ $rec->difference ? $m($rec->difference) : '' }}</span></a>
                @empty
                    <p class="text-sm text-muted">{{ __('savings::savings.none') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
