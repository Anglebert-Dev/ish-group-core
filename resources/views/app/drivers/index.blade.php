<x-layouts.app title="{{ __('body.drivers') }}">
    <x-common.page-header :title="__('body.drivers')" :description="__('body.drivers_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('drivers.export.pdf', request()->query())"
                :excel="route('drivers.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('drivers.create') }}" size="sm">+ {{ __('body.add_driver') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.license_number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.license_expiry') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.availability_status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($drivers as $driver)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('drivers.show', $driver) }}" class="hover:text-brand">{{ $driver->fullName() }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $driver->phone }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $driver->license_number }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="space-y-1">
                                <p>{{ $driver->license_expiry?->format('d M Y') ?? '—' }}</p>
                                @if ($driver->licenseAlertLevel()->isAttention() || $driver->licenseAlertLevel() === \App\Enums\DocumentAlertLevel::Unknown)
                                    <x-common.badge :color="$driver->licenseAlertLevel()->tone()">{{ $driver->licenseAlertLevel()->label() }}</x-common.badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5"><x-common.status :status="$driver->availability_status->label()" /></td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($driver->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('drivers.show', $driver) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('drivers.edit', $driver) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('drivers.destroy', $driver) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $driver->fullName() }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">No drivers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$drivers" />
    </div>
</x-layouts.app>
