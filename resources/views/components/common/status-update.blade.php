@props([
    'action',
    'value',
    'options',
    'can' => true,
])

@if ($can)
    <x-common.details.section
        :title="__('body.update_status')"
        icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
        tone="brand"
        {{ $attributes->class(['mb-6']) }}
    >
        <form action="{{ $action }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
            @csrf
            @method('PUT')
            <x-common.select
                name="status"
                :label="__('body.status')"
                :value="old('status', $value)"
                :options="$options"
                :hide-placeholder="true"
                required
                class="w-full sm:flex-1 min-w-0"
            />
            <x-common.button type="submit" size="sm">{{ __('body.update_status') }}</x-common.button>
        </form>
    </x-common.details.section>
@endif
