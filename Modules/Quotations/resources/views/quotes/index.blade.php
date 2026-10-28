<x-layouts.app :title="__('quotations::quotations.quotes')">
    <x-common.page-header :title="__('quotations::quotations.quotes')" :description="__('quotations::quotations.quotes_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create quotations.quote'))
                <x-common.button href="{{ route('quotations.quotes.create', ['sample' => 1]) }}" variant="secondary" size="sm">+ {{ __('quotations::quotations.add_sample') }}</x-common.button>
                <x-common.button href="{{ route('quotations.quotes.create') }}" size="sm">+ {{ __('quotations::quotations.add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('quotations.quotes.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('quotations::quotations.search_placeholder')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + collect(\Modules\Quotations\Enums\QuoteStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-common.select name="kind" :label="__('quotations::quotations.kind')" :value="$filters['kind'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'quote' => __('quotations::quotations.kind_quote'), 'sample' => __('quotations::quotations.kind_sample')]" />
        <x-common.select name="follow_up" :label="__('quotations::quotations.follow_up_date')" :value="$filters['follow_up'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), '1' => __('quotations::quotations.follow_up_only')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('quotations::quotations.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('quotations::quotations.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('quotations::quotations.quote_date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('quotations::quotations.follow_up_date') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.total') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($quotes as $quote)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <a href="{{ route('quotations.quotes.show', $quote) }}" class="font-medium text-ink hover:text-brand">{{ $quote->number }}</a>
                            @if ($quote->is_sample)<p class="text-xs text-muted">{{ __('quotations::quotations.sample') }}</p>@endif
                        </td>
                        <td class="px-5 py-3.5">{{ $quote->customer?->displayName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $quote->quote_date->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap {{ $quote->needsFollowUp() ? 'text-warning font-semibold' : '' }}">{{ $quote->follow_up_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right">{{ money($quote->total) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.badge :color="$quote->status->color()">{{ $quote->status->label() }}</x-common.badge>
                            @if ($quote->is_sample && $quote->sample_status)<x-common.badge :color="$quote->sample_status->color()">{{ $quote->sample_status->label() }}</x-common.badge>@endif
                            @if ($quote->isExpired())<x-common.badge color="danger">{{ __('quotations::quotations.expired') }}</x-common.badge>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('quotations::quotations.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$quotes" />
    </div>
</x-layouts.app>
