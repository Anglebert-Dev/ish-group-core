<x-layouts.app :title="__('body.website_messages')">
    <x-common.page-header :title="__('body.website_messages')" :description="__('body.website_messages_description')" />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.contact_subject') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.when') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($messages as $message)
                    <tr class="hover:bg-surface {{ $message->read_at ? '' : 'font-semibold' }}">
                        <td class="px-5 py-3.5 text-ink whitespace-nowrap">
                            @unless ($message->read_at)<span class="inline-block h-2 w-2 rounded-full bg-brand mr-1.5"></span>@endunless
                            {{ $message->name }}
                        </td>
                        <td class="px-5 py-3.5">{{ \Illuminate\Support\Str::limit($message->subject ?: $message->message, 60) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $message->email }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted">@datetime($message->created_at)</td>
                        <td class="px-5 py-3.5"><x-common.action-button :href="route('frontend.admin.messages.show', $message)">{{ __('body.view') }}</x-common.action-button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.contact_no_messages') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$messages" class="border-t border-line" />
    </div>
</x-layouts.app>
