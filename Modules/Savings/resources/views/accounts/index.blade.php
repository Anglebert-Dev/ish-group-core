@php $m = fn ($v, $c = null) => \Modules\Savings\Support\Money::format((int) $v, $c); @endphp
<x-layouts.app :title="__('savings::savings.accounts')">
    <x-common.page-header :title="__('savings::savings.accounts')" />
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted uppercase border-b border-line bg-surface/60">
                    <th class="px-5 py-3">{{ __('savings::savings.account') }}</th><th class="px-5 py-3 text-right">{{ __('savings::savings.balance') }}</th><th class="px-5 py-3 text-right">{{ __('savings::savings.available') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($accounts as $account)
                        <tr class="{{ $account->isArchived() ? 'opacity-60' : '' }}">
                            <td class="px-5 py-3"><a href="{{ route('savings.accounts.show', $account) }}" class="font-medium text-ink hover:text-brand">{{ $account->name }}</a>
                                <span class="block text-xs text-muted">{{ __('savings::savings.account_'.$account->type) }} · {{ $account->currency }}{{ $account->institution ? ' · '.$account->institution : '' }}{{ $account->isArchived() ? ' · '.__('savings::savings.archived') : '' }}</span></td>
                            <td class="px-5 py-3 text-right font-semibold">{{ $m($balances[$account->id], $account->currency) }}</td>
                            <td class="px-5 py-3 text-right">{{ isset($available[$account->id]) && $account->is_liquid ? $m($available[$account->id], $account->currency) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (\App\Helpers\AuthHelper::can('create savings.account'))
            <form action="{{ route('savings.accounts.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.account_add') }}</h2>
                @include('savings::accounts.form-fields', ['account' => null, 'prefix' => 'new-'])
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
