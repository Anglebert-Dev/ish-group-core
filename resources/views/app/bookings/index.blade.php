<x-layouts.app title="{{ __('body.bookings') }}">
    <x-common.page-header :title="__('body.bookings')" :description="__('body.bookings_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('bookings.export.pdf', request()->query())"
                :excel="route('bookings.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('bookings.create') }}" size="sm">+ {{ __('body.add_booking') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.booking_number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.category') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.pickup_datetime') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.return_datetime') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.total_amount') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.view') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($bookings as $booking)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->booking_number }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ $booking->customerName() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $booking->category?->name }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@kigali($booking->pickup_at)</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@kigali($booking->return_at)</td>
                        <td class="px-5 py-3.5 text-ink whitespace-nowrap">{{ number_format($booking->total_amount, 0) }} RWF</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$booking->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('bookings.show', $booking) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('body.no_bookings_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$bookings" />
    </div>
</x-layouts.app>
