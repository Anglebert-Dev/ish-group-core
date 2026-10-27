<x-layouts.app title="{{ __('body.extras') }}">
    <x-common.page-header :title="__('body.extras')" :description="__('body.extras_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('extras.export.pdf', request()->query())"
                :excel="route('extras.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('extras.create') }}" size="sm">+ {{ __('body.add_extra') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.extra_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.extra_type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.price') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($extras as $extra)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('extras.show', $extra) }}" class="hover:text-brand">{{ $extra->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $extra->type->label() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">
                            {{ number_format($extra->price, 0) }} RWF
                            @if ($extra->isChauffeur())
                                <span class="text-xs">{{ __('body.price_per_day_short') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($extra->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('extras.show', $extra) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('extras.edit', $extra) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('extras.destroy', $extra) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $extra->name }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_extras_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$extras" />
    </div>
</x-layouts.app>
