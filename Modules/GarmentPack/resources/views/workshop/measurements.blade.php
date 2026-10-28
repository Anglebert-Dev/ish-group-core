@php $canEdit = \App\Helpers\AuthHelper::can('update customers.customer'); @endphp
<div class="bg-white border border-line rounded-xl p-5 space-y-4">
    <div>
        <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.measurements') }}</h2>
        <p class="text-sm text-muted">{{ __('garmentpack::garment.measurements_hint') }}</p>
    </div>
    @forelse ($measurements as $set)
        <div class="rounded-xl border border-line p-4 space-y-2">
            <div class="flex items-center justify-between gap-3">
                <p class="font-semibold text-ink">{{ $set->name }} <span class="text-xs text-muted font-normal">{{ $set->taken_on?->format('d/m/Y') }}</span></p>
                @if ($canEdit)
                    <form action="{{ route('garmentpack.measurements.destroy', $set) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-danger" data-confirm data-confirm-title="{{ __('body.delete') }}?">{{ __('body.delete') }}</button>
                    </form>
                @endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                @foreach ($set->values as $label => $value)
                    <div><span class="text-muted">{{ $label }}:</span> <b>{{ $value }}</b></div>
                @endforeach
            </div>
            @if ($set->notes)<p class="text-xs text-muted">{{ $set->notes }}</p>@endif
        </div>
    @empty
        <p class="text-sm text-muted">{{ __('garmentpack::garment.measurements_none') }}</p>
    @endforelse
    @if ($canEdit)
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-brand">+ {{ __('garmentpack::garment.measurement_add') }}</summary>
            <form action="{{ route('garmentpack.measurements.store', $customer) }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-common.input name="name" id="m-name" :label="__('garmentpack::garment.measurement_name')" :hint="__('garmentpack::garment.measurement_name_hint')" required />
                    <x-common.input name="taken_on" id="m-taken" type="date" :label="__('garmentpack::garment.taken_on')" :value="now()->toDateString()" />
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($fields as $field)
                        <x-common.input :name="'values['.$field.']'" :id="'m-'.\Illuminate\Support\Str::slug($field)" :label="$field" compact />
                    @endforeach
                </div>
                <x-common.input name="notes" id="m-notes" :label="__('garmentpack::garment.notes')" />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        </details>
    @endif
</div>
