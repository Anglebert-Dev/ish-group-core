<x-layouts.app :title="__('savings::savings.settings')">
    <x-common.page-header :title="__('savings::savings.settings')" :description="__('savings::savings.settings_description')" />
    <form action="{{ route('savings.settings.update') }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-2xl">
        @csrf @method('PUT')
        <x-common.select name="currency" :label="__('savings::savings.base_currency')" :value="$currency" :options="array_combine($currencies, $currencies)" hide-placeholder required />
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-common.select name="period_type" :label="__('savings::savings.period_type')" :value="$period['type']" :options="['monthly' => __('savings::savings.period_monthly'), 'weekly' => __('savings::savings.period_weekly'), 'biweekly' => __('savings::savings.period_biweekly')]" hide-placeholder />
            <x-common.input name="start_day" type="number" min="1" max="31" :label="__('savings::savings.start_day')" :value="$period['start_day']" :hint="__('savings::savings.start_day_hint')" />
            <x-common.select name="weekday" :label="__('savings::savings.weekday')" :value="$period['weekday']" :options="collect(range(1, 7))->mapWithKeys(fn ($d) => [$d => \Illuminate\Support\Carbon::create(2024, 1, $d)->dayName])->all()" hide-placeholder />
            <x-common.input name="anchor" type="date" :label="__('savings::savings.anchor')" :value="$period['anchor']" />
        </div>
        <div>
            <p class="text-sm font-semibold text-ink mb-2">{{ __('savings::savings.available_policy') }}</p>
            @foreach (['goal_earmarks', 'minimum_balance'] as $key)
                <input type="hidden" name="{{ $key }}" value="0">
                <x-common.checkbox :name="$key" value="1" :label="__('savings::savings.policy_'.$key)" :checked="(bool) $available[$key]" />
            @endforeach
        </div>
        <div>
            <p class="text-sm font-semibold text-ink mb-2">{{ __('savings::savings.alerts') }}</p>
            @foreach ($alerts as $key => $on)
                <input type="hidden" name="alert_{{ $key }}" value="0">
                <x-common.checkbox :name="'alert_'.$key" value="1" :label="__('savings::savings.alertopt_'.$key)" :checked="(bool) $on" />
            @endforeach
        </div>
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
