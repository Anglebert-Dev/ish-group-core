@php
    $assigned = $assigned ?? [];
    $actions = config('access.access_actions', []);
    $moduleLabels = $catalogue['module_labels'] ?? [];
    $controllerLabels = $catalogue['controller_labels'] ?? [];
@endphp

<div class="space-y-6">
    @foreach ($catalogue['modules'] as $module => $controllers)
        <div class="border border-line rounded-lg overflow-hidden">
            <div class="bg-surface px-4 py-2.5 border-b border-line flex items-center justify-between">
                <p class="font-semibold text-ink text-sm">{{ $moduleLabels[$module] ?? Str::headline($module) }}</p>
                <label class="flex items-center gap-2 text-xs text-muted cursor-pointer">
                    <input type="checkbox" class="module-select-all rounded border-line text-brand" data-module="{{ $module }}">
                    {{ __('body.select_all') }}
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted border-b border-line">
                            <th class="px-4 py-2 font-medium whitespace-nowrap">{{ __('body.permission_group') }}</th>
                            @foreach ($actions as $action)
                                <th class="px-2 py-2 font-medium text-center whitespace-nowrap">{{ Str::headline($action) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($controllers as $controller => $permissions)
                            <tr>
                                <td class="px-4 py-2 text-body whitespace-nowrap">{{ $controllerLabels[$controller] ?? Str::headline($controller) }}</td>
                                @foreach ($actions as $action)
                                    @php $permName = "{$action} {$module}.{$controller}"; @endphp
                                    <td class="px-2 py-2 text-center">
                                        <input type="checkbox" name="permissions[]" value="{{ $permName }}"
                                               class="module-checkbox-{{ $module }} rounded border-line text-brand"
                                               {{ in_array($permName, $assigned, true) ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if (! empty($catalogue['extra']))
        <div class="border border-line rounded-xl p-4">
            <p class="font-semibold text-ink text-sm mb-3">{{ __('body.other_permissions') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($catalogue['extra'] as $perm)
                    <x-common.checkbox
                        name="permissions[]"
                        :value="$perm"
                        :label="Str::headline($perm)"
                        :checked="in_array($perm, $assigned, true)"
                    />
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
    document.querySelectorAll('.module-select-all').forEach(function (master) {
        var boxes = document.querySelectorAll('.module-checkbox-' + master.dataset.module);
        master.checked = Array.from(boxes).every(function (cb) { return cb.checked; });

        master.addEventListener('change', function () {
            boxes.forEach(function (cb) { cb.checked = master.checked; });
        });
    });
</script>
