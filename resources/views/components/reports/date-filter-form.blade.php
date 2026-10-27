@props(['from', 'to', 'exportPdf', 'exportExcel'])

<form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-end justify-between">
    <div class="flex flex-wrap gap-3 items-end">
        <x-common.input type="date" name="from" :label="__('body.from')" :value="$from" />
        <x-common.input type="date" name="to" :label="__('body.to')" :value="$to" />
        <x-common.button type="submit" size="sm">{{ __('body.filter') }}</x-common.button>
    </div>
    <x-common.export-buttons :pdf="$exportPdf" :excel="$exportExcel" />
</form>
