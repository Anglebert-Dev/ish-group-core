@php
    $account = $account ?? null;
    $cancelHref = $cancelHref ?? route('finance.chart-of-accounts.index');
    $parentOptions = collect($parents ?? [])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all();
@endphp

<x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="code" :label="__('body.account_code')" :value="old('code', $account?->code)" required hint="e.g. 4.1.4" />
        <x-common.input name="name" :label="__('body.account_name')" :value="old('name', $account?->name)" required />
        <x-common.select name="account_type" :label="__('body.account_type')" :value="old('account_type', $account?->account_type?->value)" :options="\App\Enums\AccountType::cases()" :hide-placeholder="true" required />
        <x-common.select name="parent_id" :label="__('body.parent_account')" :value="old('parent_id', $account?->parent_id)" :options="$parentOptions" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $account?->status?->value ?? \App\Enums\ChartOfAccountStatus::Active->value)" :options="\App\Enums\ChartOfAccountStatus::cases()" :hide-placeholder="true" />
        <div class="sm:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="is_posting" value="1" class="rounded border-line" @checked(old('is_posting', $account?->is_posting ?? true))>
                {{ __('body.posting_account') }}
            </label>
        </div>
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $account?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.account_code')" for="code" />
        <x-common.wizard.review-row :label="__('body.account_name')" for="name" />
        <x-common.wizard.review-row :label="__('body.account_type')" for="account_type" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
