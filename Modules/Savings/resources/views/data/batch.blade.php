<x-layouts.app :title="__('savings::savings.import')">
    <x-common.page-header :title="$batch->filename" :description="$account?->name">
        <x-slot:actions><x-common.button href="{{ route('savings.data.index') }}" variant="secondary" size="sm">← {{ __('savings::savings.data') }}</x-common.button></x-slot:actions>
    </x-common.page-header>
    <form action="{{ route('savings.data.commit', $batch) }}" method="POST" class="space-y-4">
        @csrf
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @foreach ($batch->rows as $i => $row)
                        <tr>
                            <td class="px-4 py-2 text-muted">{{ $row['line'] }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $row['date'] ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $row['description'] }} <span class="text-xs text-muted">{{ $row['reference'] }}</span></td>
                            <td class="px-4 py-2 text-right">@include('savings::partials.money', ['v' => $row['amount'] ?? 0, 'c' => $account?->currency, 'signed' => true])</td>
                            <td class="px-4 py-2"><x-common.badge :color="match ($row['status']) { 'ok' => 'success', 'imported' => 'brand', 'duplicate' => 'warning', default => 'danger' }">{{ __('savings::savings.row_'.$row['status']) }}</x-common.badge>
                                <span class="block text-xs text-muted">{{ $row['error'] ?? '' }}</span>
                                @if ($batch->status === 'preview' && $row['status'] === 'duplicate' && ! $row['exact'])
                                    <label class="text-xs"><input type="checkbox" name="also[]" value="{{ $i }}"> {{ __('savings::savings.import_anyway') }}</label>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($batch->status === 'preview')
            <div class="bg-white border border-line rounded-xl p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <x-common.select name="income_category" :label="__('savings::savings.import_income_category')" :options="$income->mapWithKeys(fn ($c) => [$c->uuid => $c->name])->all()" hide-placeholder required />
                <x-common.select name="expense_category" :label="__('savings::savings.import_expense_category')" :options="$expense->mapWithKeys(fn ($c) => [$c->uuid => $c->name])->all()" hide-placeholder required />
                <x-common.button type="submit">{{ __('savings::savings.import_confirm') }}</x-common.button>
            </div>
        @endif
    </form>
</x-layouts.app>
