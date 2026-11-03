<form method="GET" class="flex flex-wrap gap-2 items-end mb-6">
    <x-common.input name="from" type="date" :label="__('injongepay::injongepay.from')" :value="$from->toDateString()" compact />
    <x-common.input name="to" type="date" :label="__('injongepay::injongepay.to')" :value="$to->toDateString()" compact />
    <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.apply') }}</x-common.button>
    {{ $slot ?? '' }}
</form>
