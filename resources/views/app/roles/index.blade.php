<x-layouts.app title="{{ __('body.roles') }}">
    <x-common.page-header :title="__('body.roles')" :description="__('body.roles_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('roles.export.pdf', request()->query())"
                :excel="route('roles.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('roles.create') }}" size="sm">+ {{ __('body.add_role') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.role_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.permissions') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($roles as $role)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('roles.show', $role) }}" class="hover:text-brand">{{ $role->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-muted">{{ $role->permissions_count }} {{ __('body.permissions') }}</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('roles.show', $role) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            @if ($role->name !== 'SuperAdmin')
                                <span class="text-line mx-1.5">|</span>
                                <a href="{{ route('roles.edit', $role) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                                <span class="text-line mx-1.5">|</span>
                                <form action="{{ route('roles.destroy', $role) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                        data-confirm
                                        data-confirm-title="{{ __('body.delete') }} {{ $role->name }}?"
                                        data-confirm-message="Staff currently assigned this role will lose its permissions."
                                        data-confirm-variant="danger"
                                        data-confirm-text="{{ __('body.delete') }}"
                                    >{{ __('body.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</x-layouts.app>
