@php $acc = $account ?? null; $locked = $acc && $acc->entries()->exists(); $c = $acc?->currency ?? \Modules\Savings\Support\Money::baseCurrency(); @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <x-common.input name="name" :id="$prefix.'name'" :label="__('body.name')" :value="old('name', $acc?->name)" required />
    <x-common.select name="type" :id="$prefix.'type'" :label="__('savings::savings.type')" :value="old('type', $acc?->type ?? 'bank')" hide-placeholder required
        :options="collect(\Modules\Savings\Http\Controllers\AccountController::TYPES)->mapWithKeys(fn ($t) => [$t => __('savings::savings.account_'.$t)])->all()" />
    <x-common.input name="currency" :id="$prefix.'currency'" :label="__('savings::savings.currency')" :value="old('currency', $c)" maxlength="3" required :readonly="$locked" />
    <x-common.input name="opening_date" :id="$prefix.'opening_date'" type="date" :label="__('savings::savings.opening_date')" :value="old('opening_date', $acc?->opening_date?->toDateString() ?? now()->toDateString())" required :readonly="$locked" />
    <x-common.input name="opening_balance" :id="$prefix.'opening_balance'" :label="__('savings::savings.opening_balance')" :value="old('opening_balance', $acc ? \Modules\Savings\Support\Money::toDecimal($acc->opening_balance, $acc->currency) : '0')" :hint="__('savings::savings.opening_balance_hint')" :readonly="$locked" />
    <x-common.input name="minimum_balance" :id="$prefix.'minimum_balance'" :label="__('savings::savings.minimum_balance')" :value="old('minimum_balance', $acc?->minimum_balance !== null ? \Modules\Savings\Support\Money::toDecimal($acc->minimum_balance, $acc->currency) : '')" :hint="__('savings::savings.minimum_balance_hint')" />
    <x-common.input name="institution" :id="$prefix.'institution'" :label="__('savings::savings.institution')" :value="old('institution', $acc?->institution)" />
    <x-common.input name="reference" :id="$prefix.'reference'" :label="__('savings::savings.account_reference')" :value="old('reference', $acc?->reference)" />
</div>
<input type="hidden" name="is_liquid" value="0">
<x-common.checkbox name="is_liquid" :id="$prefix.'is_liquid'" value="1" :label="__('savings::savings.is_liquid')" :checked="(bool) old('is_liquid', $acc?->is_liquid ?? true)" />
<x-common.textarea name="notes" :id="$prefix.'notes'" :label="__('savings::savings.notes')" :value="old('notes', $acc?->notes)" :rows="2" />
