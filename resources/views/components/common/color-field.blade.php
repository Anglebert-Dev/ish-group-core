@props(['name', 'label', 'value' => null, 'default' => '#000000', 'hint' => null])

{{-- A colour: swatch + hex. Blank means "use the default", shown as the placeholder. --}}
@php $id = 'color-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name)); @endphp
<div class="flex min-w-0 flex-col gap-1.5" data-color-field>
    <label for="{{ $id }}" class="text-sm font-semibold text-ink">{{ $label }}</label>
    <div class="flex items-center gap-2">
        <input type="color" value="{{ $value ?: $default }}" aria-label="{{ $label }}"
               class="h-10 w-12 shrink-0 rounded-lg border border-line bg-card p-1 cursor-pointer"
               oninput="var t = this.parentElement.querySelector('input[type=text]'); t.value = this.value.toUpperCase(); t.dispatchEvent(new Event('input', {bubbles: true}))">
        <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" placeholder="{{ $default }}" maxlength="7"
               class="flex-1 min-w-0 h-10 rounded-lg border border-line bg-card px-3 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
               oninput="if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) this.parentElement.querySelector('input[type=color]').value = this.value; else if (this.value === '') this.parentElement.querySelector('input[type=color]').value = this.placeholder">
    </div>
    @if ($hint)<p class="text-xs text-muted">{{ $hint }}</p>@endif
</div>
