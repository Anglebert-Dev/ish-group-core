<x-layouts.app :title="__('body.communicator')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.communicator')" :description="__('body.communicator_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
        <form action="{{ route('superadmin.communicator.send') }}" method="POST" class="xl:col-span-3 bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            @csrf
            <x-common.select name="audience" :label="__('body.communicator_audience')" :value="old('audience', 'active')" hide-placeholder
                :options="['active' => __('body.communicator_active'), 'all' => __('body.communicator_all'), 'selected' => __('body.communicator_selected')]" />
            <div>
                <label class="text-sm font-semibold text-ink">{{ __('body.businesses') }}</label>
                <p class="text-xs text-muted mb-2">{{ __('body.communicator_selected_hint') }}</p>
                <select name="business_ids[]" multiple size="6" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                    @foreach ($businesses as $id => $name)
                        <option value="{{ $id }}" @selected(in_array($id, old('business_ids', [])))>{{ $name }}</option>
                    @endforeach
                </select>
                @error('business_ids')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <x-common.input name="subject" :label="__('body.contact_subject')" :value="old('subject')" required />
            <x-common.textarea name="body" :label="__('body.message')" :value="old('body')" :rows="8" required />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="hidden" name="send_in_app" value="0">
                <x-common.checkbox name="send_in_app" :label="__('body.communicator_in_app')" :checked="(bool) old('send_in_app', true)" />
                <input type="hidden" name="send_email" value="0">
                <x-common.checkbox name="send_email" :label="__('body.communicator_email')" :checked="(bool) old('send_email', true)" />
            </div>
            @error('send_in_app')<p class="text-xs text-danger">{{ $message }}</p>@enderror
            <x-common.button type="submit" data-confirm data-confirm-title="{{ __('body.communicator_confirm') }}">{{ __('body.communicator_send') }}</x-common.button>
        </form>

        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('body.communicator_history') }}</h2></div>
            <ul class="divide-y divide-line">
                @forelse ($history as $item)
                    <li class="px-5 py-3.5">
                        <p class="font-medium text-ink">{{ $item->subject }}</p>
                        <p class="text-xs text-muted mt-0.5">@datetime($item->created_at) · {{ trans_choice('body.communicator_n_recipients', $item->recipients_count, ['count' => $item->recipients_count]) }} · {{ $item->sender?->name }}</p>
                        <p class="text-sm text-body mt-1">{{ \Illuminate\Support\Str::limit($item->body, 140) }}</p>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-muted">{{ __('body.communicator_none') }}</li>
                @endforelse
            </ul>
            <x-common.pagination :paginator="$history" class="border-t border-line" />
        </div>
    </div>
</x-layouts.app>
