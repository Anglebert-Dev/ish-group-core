@php
    $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name.' ('.$a->currency.')'])->all();
    $income = $categories->where('kind', 'income')->mapWithKeys(fn ($c) => [$c->uuid => $c->fullName()])->all();
    $expense = $categories->where('kind', 'expense')->mapWithKeys(fn ($c) => [$c->uuid => $c->fullName()])->all();
    $type = old('type', $type);
    $prefill = $replaces;
    $prefillAccount = $prefill?->entries->sortBy('amount')->first()?->account?->uuid;
    $prefillTo = $prefill?->entries->where('amount', '>', 0)->first()?->account?->uuid;
    $prefillCategory = $prefill?->lines->firstWhere('category_id', '!=', null)?->category?->uuid;
    $prefillAmount = $prefill ? \Modules\Savings\Support\Money::toDecimal($prefill->amount, $prefill->currency) : null;
@endphp
<x-layouts.app :title="__('savings::savings.new')">
    <x-common.page-header :title="$replaces ? __('savings::savings.correct').': '.$replaces->description : __('savings::savings.new')">
        <x-slot:actions><x-common.button href="{{ route('savings.transactions.index') }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button></x-slot:actions>
    </x-common.page-header>

    <form action="{{ route('savings.transactions.store') }}" method="POST" class="space-y-6 max-w-4xl" data-tx-form>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $key }}">
        @if ($replaces)<input type="hidden" name="replaces" value="{{ $replaces->uuid }}">@endif
        @include('savings::partials.duplicates')

        <div class="bg-white border border-line rounded-xl p-5 space-y-3">
            <p class="text-sm font-semibold text-ink">{{ __('savings::savings.pick_type') }}</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                @foreach (\Modules\Savings\Http\Controllers\TransactionController::FORM_TYPES as $t)
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="{{ $t }}" class="peer sr-only" @checked($type === $t)>
                        <span class="block rounded-lg border border-line p-3 peer-checked:border-brand peer-checked:bg-brand-light">
                            <span class="block text-sm font-semibold text-ink">{{ __('savings::savings.type_'.$t) }}</span>
                            <span class="block text-xs text-muted">{{ __('savings::savings.help_'.$t) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-common.select name="account" :label="__('savings::savings.account')" :value="old('account', $prefillAccount)" :options="$accountOptions" hide-placeholder required />
                <div data-show="transfer"><x-common.select name="to_account" :label="__('savings::savings.to_account')" :value="old('to_account', $prefillTo)" :options="['' => '—'] + $accountOptions" hide-placeholder /></div>
                <x-common.input name="amount" :label="__('savings::savings.amount')" :value="old('amount', $prefillAmount)" required inputmode="decimal" data-label-split="{{ __('savings::savings.real_cost') }}" />
                <x-common.input name="effective_date" type="date" :label="__('savings::savings.date')" :value="old('effective_date', $prefill?->effective_date?->toDateString() ?? now()->toDateString())" required />
                <div data-show="split"><x-common.input name="owed_back" :label="__('savings::savings.owed_back')" :value="old('owed_back')" inputmode="decimal" /></div>
                <div data-show="income"><x-common.select name="category_income" :label="__('savings::savings.category')" :value="old('category', $prefillCategory)" :options="['' => '—'] + $income" hide-placeholder /></div>
                <div data-show="expense split"><x-common.select name="category_expense" :label="__('savings::savings.category')" :value="old('category', $prefillCategory)" :options="['' => '—'] + $expense" hide-placeholder /></div>
                <div data-show="transfer"><x-common.input name="received" :label="__('savings::savings.received_amount')" :value="old('received')" :hint="__('savings::savings.received_amount_hint')" /></div>
                <div data-show="transfer"><x-common.input name="fee" :label="__('savings::savings.fee')" :value="old('fee')" /></div>
                <div data-show="transfer"><x-common.select name="fee_category" :label="__('savings::savings.fee_category')" :value="old('fee_category')" :options="['' => '—'] + $expense" hide-placeholder /></div>
                <x-common.input name="description" :label="__('savings::savings.description')" :value="old('description', $prefill?->description)" />
                <x-common.input name="counterparty" :label="__('savings::savings.counterparty')" :value="old('counterparty', $prefill?->counterparty)" />
                <div data-show="split advance borrowing"><x-common.input name="due_date" type="date" :label="__('savings::savings.due_date')" :value="old('due_date')" /></div>
                <x-common.input name="reference" :label="__('savings::savings.reference')" :value="old('reference', $prefill?->reference)" />
                <x-common.input name="tags" :label="__('savings::savings.tags')" :value="old('tags', $prefill ? implode(', ', $prefill->tags ?? []) : '')" :hint="__('savings::savings.tags_hint')" />
            </div>
            <details data-show="income expense" class="rounded-lg border border-dashed border-line p-3">
                <summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('savings::savings.foreign') }}</summary>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <x-common.input name="original_amount" :label="__('savings::savings.original_amount')" :value="old('original_amount')" />
                    <x-common.input name="original_currency" :label="__('savings::savings.original_currency')" :value="old('original_currency')" maxlength="3" />
                </div>
            </details>
            <x-common.textarea name="notes" :label="__('savings::savings.notes')" :value="old('notes')" :rows="2" />
            <input type="hidden" name="category" value="{{ old('category', $prefillCategory) }}" data-category>
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-tx-form]');
                function type() { return form.querySelector('input[name="type"]:checked').value; }
                function refresh() {
                    var t = type();
                    form.querySelectorAll('[data-show]').forEach(function (el) { el.classList.toggle('hidden', el.dataset.show.split(' ').indexOf(t) < 0); });
                    var label = form.querySelector('[name="amount"]').closest('div').querySelector('label');
                    if (label) label.firstChild.textContent = (t === 'split' ? @json(__('savings::savings.real_cost')) : @json(__('savings::savings.amount'))) + ' ';
                }
                form.querySelectorAll('input[name="type"]').forEach(function (r) { r.addEventListener('change', refresh); });
                form.addEventListener('submit', function () {
                    var t = type();
                    var source = t === 'income' ? 'category_income' : 'category_expense';
                    form.querySelector('[data-category]').value = form.querySelector('[name="' + source + '"]').value;
                });
                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
