<div data-repeater-row class="relative rounded-xl border border-line bg-surface p-4 space-y-3">
    <button type="button" data-repeater-remove class="absolute top-3 right-3 text-xs font-semibold text-danger hover:opacity-80">{{ __('body.remove') }}</button>
    @foreach ($fields as $key => $field)
        @php
            $fieldName = $name.'['.$index.']['.$key.']';
            $value = old(str_replace(['[', ']'], ['.', ''], $fieldName), $row[$key] ?? '');
        @endphp
        @if (($field['type'] ?? 'text') === 'textarea')
            <x-common.textarea :name="$fieldName" :label="$field['label']" :value="$value" :rows="$field['rows'] ?? 3" />
        @else
            <x-common.input :name="$fieldName" :label="$field['label']" :value="$value" class="pr-16" />
        @endif
    @endforeach
</div>
