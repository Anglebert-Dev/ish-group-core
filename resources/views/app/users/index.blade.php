<x-layouts.app title="{{ __('body.staff') }}">
    <x-common.page-header :title="__('body.staff')" :description="__('body.staff_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('users.export.pdf', request()->query())"
                :excel="route('users.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('users.create') }}" size="sm">+ {{ __('body.add_staff') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.role') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($users as $u)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('users.show', $u) }}" class="hover:text-brand">{{ $u->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $u->email }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $u->roles->first()?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($u->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('users.show', $u) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('users.edit', $u) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>

                            @unless (auth('staff')->user()->is($u))
                                <span class="text-line mx-1.5">|</span>
                                @if ($u->status->value === 'pending')
                                    <form action="{{ route('users.status', $u) }}" method="POST" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="text-success hover:opacity-80 font-medium text-sm">{{ __('body.approve') }}</button>
                                    </form>
                                @elseif ($u->status->value === 'active')
                                    <form action="{{ route('users.status', $u) }}" method="POST" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="banned">
                                        <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                            data-confirm
                                            data-confirm-title="{{ __('body.ban') }} {{ $u->name }}?"
                                            data-confirm-message="They will be signed out and unable to log in until reactivated."
                                            data-confirm-variant="danger"
                                            data-confirm-text="{{ __('body.ban') }}"
                                        >{{ __('body.ban') }}</button>
                                    </form>
                                @else
                                    <form action="{{ route('users.status', $u) }}" method="POST" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="text-success hover:opacity-80 font-medium text-sm">{{ __('body.reactivate') }}</button>
                                    </form>
                                @endif
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">No staff yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$users" class="border-t border-line" />
    </div>
</x-layouts.app>
