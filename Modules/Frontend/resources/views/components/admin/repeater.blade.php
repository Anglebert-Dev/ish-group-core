@props([
    'name',              // e.g. "home[features][items]"
    'rows' => [],        // current rows
    'fields',            // ['title' => ['label' => ..., 'type' => 'text'|'textarea']]
    'addLabel',
    'max' => 30,
])

@php $repeaterId = 'repeater-'.\Illuminate\Support\Str::random(6); @endphp

<div id="{{ $repeaterId }}" data-repeater data-max="{{ $max }}" class="space-y-3">
    <div data-repeater-rows class="space-y-3">
        @foreach (array_values($rows) as $index => $row)
            @include('frontend::components.admin.repeater-row', ['index' => $index, 'row' => $row])
        @endforeach
    </div>

    <template data-repeater-template>
        @include('frontend::components.admin.repeater-row', ['index' => '__INDEX__', 'row' => []])
    </template>

    <button type="button" data-repeater-add class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:text-brand-dark">
        + {{ $addLabel }}
    </button>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
                var rows = repeater.querySelector('[data-repeater-rows]');
                var template = repeater.querySelector('[data-repeater-template]');
                var max = parseInt(repeater.dataset.max || '30', 10);
                var next = rows.children.length;

                repeater.querySelector('[data-repeater-add]').addEventListener('click', function () {
                    if (rows.children.length >= max) return;
                    var html = template.innerHTML.replace(/__INDEX__/g, String(next++));
                    rows.insertAdjacentHTML('beforeend', html);
                });

                rows.addEventListener('click', function (event) {
                    var remove = event.target.closest('[data-repeater-remove]');
                    if (remove) remove.closest('[data-repeater-row]').remove();
                });
            });
        </script>
    @endpush
@endonce
