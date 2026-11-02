<form action="{{ route('superadmin.updates.link') }}" method="POST" class="space-y-2">
    @csrf
    <p class="font-medium text-ink">{{ __('updates.link_title') }}</p>
    <p class="text-xs text-muted">{{ __('updates.link_hint') }}</p>
    <x-common.textarea name="key" :rows="3" :placeholder="'ISH1-…'" required />
    <x-common.button type="submit" size="sm">{{ __('updates.link_button') }}</x-common.button>
</form>
