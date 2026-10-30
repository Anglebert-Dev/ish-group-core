@php $c = $rule?->account?->currency ?? \Modules\Savings\Support\Money::baseCurrency(); @endphp
<x-common.select name="kind" :id="$p.'kind'" :label="__('savings::savings.kind')" :value="$rule?->kind" :options="$kinds" hide-placeholder required compact />
<x-common.input name="description" :id="$p.'desc'" :label="__('savings::savings.description')" :value="$rule?->description" required compact />
<x-common.select name="account" :id="$p.'acc'" :label="__('savings::savings.account')" :value="$rule?->account?->uuid" :options="$accountOptions" hide-placeholder required compact />
<x-common.select name="to_account" :id="$p.'to'" :label="__('savings::savings.to_account')" :value="$accounts->firstWhere('id', $rule?->to_account_id)?->uuid" :options="['' => '—'] + $accountOptions" hide-placeholder compact />
<x-common.select name="category" :id="$p.'cat'" :label="__('savings::savings.category')" :value="$categories->firstWhere('id', $rule?->category_id)?->uuid" :options="['' => '—'] + $catOptions" hide-placeholder compact />
<x-common.select name="goal" :id="$p.'goal'" :label="__('savings::savings.goal')" :value="$goals->firstWhere('id', $rule?->goal_id)?->uuid" :options="['' => '—'] + $goalOptions" hide-placeholder compact />
<x-common.input name="amount" :id="$p.'amt'" :label="__('savings::savings.amount')" :value="$rule ? \Modules\Savings\Support\Money::toDecimal($rule->amount, $c) : ''" required compact />
<x-common.input name="interval" :id="$p.'int'" type="number" min="1" :label="__('savings::savings.interval')" :value="$rule?->interval ?? 1" required compact />
<x-common.select name="frequency" :id="$p.'freq'" :label="__('savings::savings.frequency')" :value="$rule?->frequency ?? 'month'" :options="collect(['day', 'week', 'month', 'year'])->mapWithKeys(fn ($f) => [$f => __('savings::savings.freq_'.$f)])->all()" hide-placeholder compact />
<x-common.input name="start_date" :id="$p.'start'" type="date" :label="__('savings::savings.start_date')" :value="$rule?->start_date?->toDateString() ?? now()->toDateString()" required compact />
<x-common.input name="end_date" :id="$p.'end'" type="date" :label="__('savings::savings.end_date')" :value="$rule?->end_date?->toDateString()" compact />
<x-common.select name="confidence" :id="$p.'conf'" :label="__('savings::savings.confidence')" :value="$rule?->confidence ?? 'confirmed'" :options="$confidence" hide-placeholder compact />
<input type="hidden" name="is_variable" value="0">
<x-common.checkbox name="is_variable" :id="$p.'var'" value="1" :label="__('savings::savings.is_variable')" :checked="(bool) $rule?->is_variable" />
