@props(['name', 'label', 'checked' => false, 'description' => null])

{{-- Checkbox that always submits: hidden 0, then 1 when ticked. --}}
<input type="hidden" name="{{ $name }}" value="0">
<x-common.checkbox :name="$name" value="1" :label="$label" :description="$description" :checked="$checked" />
