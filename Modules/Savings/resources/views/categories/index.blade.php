<x-layouts.app :title="__('savings::savings.categories')">
    <x-common.page-header :title="__('savings::savings.categories')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create savings.category'))
                <x-common.action-button :action="route('savings.categories.starter')">{{ __('savings::savings.starter_categories') }}</x-common.action-button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @php $parents = fn ($kind) => $categories->where('kind', $kind)->whereNull('parent_id')->mapWithKeys(fn ($c) => [$c->uuid => $c->name])->all(); @endphp
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach (['expense', 'income'] as $kind)
                <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                    <h2 class="font-display font-bold text-ink">{{ __('savings::savings.kind_'.$kind) }}</h2>
                    @foreach ($categories->where('kind', $kind) as $category)
                        <details class="border-b border-line pb-2 {{ $category->is_archived ? 'opacity-60' : '' }}">
                            <summary class="cursor-pointer text-sm">{{ $category->fullName() }} @if ($category->is_archived)<x-common.badge color="muted">{{ __('savings::savings.archived') }}</x-common.badge>@endif</summary>
                            @if (\App\Helpers\AuthHelper::can('update savings.category'))
                                <form action="{{ route('savings.categories.update', $category) }}" method="POST" class="mt-2 grid grid-cols-2 gap-2 items-end">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="kind" value="{{ $kind }}">
                                    <x-common.input name="name" :id="'c-'.$category->id" :label="__('body.name')" :value="$category->name" compact required />
                                    <x-common.select name="parent" :id="'p-'.$category->id" :label="__('savings::savings.parent')" :value="$category->parent?->uuid" :options="['' => '—'] + collect($parents($kind))->except($category->uuid)->all()" hide-placeholder compact />
                                    <input type="hidden" name="is_archived" value="0">
                                    <x-common.checkbox name="is_archived" :id="'a-'.$category->id" value="1" :label="__('savings::savings.archived')" :checked="$category->is_archived" />
                                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                                </form>
                            @endif
                        </details>
                    @endforeach
                </div>
            @endforeach
        </div>
        @if (\App\Helpers\AuthHelper::can('create savings.category'))
            <form action="{{ route('savings.categories.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.category_add') }}</h2>
                <x-common.select name="kind" :label="__('savings::savings.kind')" :options="['expense' => __('savings::savings.kind_expense'), 'income' => __('savings::savings.kind_income')]" hide-placeholder required />
                <x-common.input name="name" :label="__('body.name')" required />
                <x-common.select name="parent" :label="__('savings::savings.parent')" :options="['' => '—'] + $parents('expense') + $parents('income')" hide-placeholder />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
