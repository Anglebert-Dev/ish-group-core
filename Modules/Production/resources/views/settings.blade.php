<x-layouts.app :title="__('production::production.settings')">
    <x-common.page-header :title="__('production::production.settings')" :description="__('production::production.settings_description')" />

    <form action="{{ route('production.settings.update') }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-xl">
        @csrf @method('PUT')
        <x-common.textarea name="stages" :label="__('production::production.stages')" :value="old('stages', implode(PHP_EOL, $stages))" :rows="8" :hint="__('production::production.stages_hint')" required />
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
