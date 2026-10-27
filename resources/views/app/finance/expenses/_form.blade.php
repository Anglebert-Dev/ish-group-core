@php
    $expense = $expense ?? null;
    $cancelHref = $cancelHref ?? route('finance.expenses.index');
    $supplierOptions = collect($suppliers ?? [])->mapWithKeys(fn ($s) => [$s->id => $s->name])->all();
    $accountOptions = collect($accounts ?? [])->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all();
@endphp

<x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input type="date" name="expense_date" :label="__('body.date')" :value="old('expense_date', optional($expense?->expense_date)->format('Y-m-d') ?? now()->toDateString())" required />
        <x-common.input amount name="amount" :label="__('body.amount')" :value="old('amount', $expense?->amount)" required min="0.01" />
        <x-common.select name="supplier_id" :label="__('body.supplier')" :value="old('supplier_id', $expense?->supplier_id)" :options="$supplierOptions" />
        <x-common.select name="chart_of_account_id" :label="__('body.expense_account')" :value="old('chart_of_account_id', $expense?->chart_of_account_id)" :options="$accountOptions" />
        <x-common.input name="description" :label="__('body.description')" :value="old('description', $expense?->description)" class="sm:col-span-2" />
        <div class="sm:col-span-2 space-y-2">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="paid" value="1" class="rounded border-line" @checked(old('paid', $expense?->paid ?? false))> {{ __('body.paid_now') }}</label>
            @unless ($expense)
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="post_now" value="1" class="rounded border-line" @checked(old('post_now', true))> {{ __('body.post_to_ledger') }}</label>
            @endunless
        </div>
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $expense?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.date')" for="expense_date" />
        <x-common.wizard.review-row :label="__('body.amount')" for="amount" />
        <x-common.wizard.review-row :label="__('body.description')" for="description" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
