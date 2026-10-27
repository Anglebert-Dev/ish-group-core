<x-layouts.app title="{{ __('body.notifications') }}">
    <x-common.page-header :title="__('body.notifications')" :description="__('body.notifications_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('notifications.export.pdf', request()->query())"
                :excel="route('notifications.export.excel', request()->query())"
            />
            @if ($unreadCount > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.mark_all_as_read') }}</x-common.button>
                </form>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.notification') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.when') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($notifications as $notification)
                    <tr class="hover:bg-surface {{ $notification->isUnread() ? 'bg-brand-light/30' : '' }}">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.status :status="$notification->status->label()" />
                        </td>
                        <td class="px-5 py-3.5">
                            <a href="{{ route('notifications.show', $notification) }}" class="font-medium text-ink hover:text-brand">
                                {{ $notification->title }}
                            </a>
                            @if ($notification->body)
                                <p class="text-xs text-muted mt-0.5 line-clamp-1">{{ $notification->body }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $notification->type->label() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime($notification->created_at)</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('notifications.show', $notification) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            @if ($notification->isUnread())
                                <span class="text-line mx-1.5">|</span>
                                <form action="{{ route('notifications.read', $notification) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.mark_as_read') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_notifications_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$notifications" />
    </div>
</x-layouts.app>
