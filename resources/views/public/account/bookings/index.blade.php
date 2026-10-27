<x-layouts.public :title="__('body.my_bookings')" :panel="true">
        <x-common.page-header :title="__('body.my_bookings')" :description="__('body.my_bookings_description', ['company' => $company])">
            <x-slot:actions>
                <x-common.button href="{{ route('booking.search') }}" size="sm">{{ __('body.book_now') }}</x-common.button>
            </x-slot:actions>
        </x-common.page-header>

        <x-common.timezone-note class="mb-5" />

        @if ($bookings->isEmpty())
            <div class="bg-white border border-line rounded-xl p-12 text-center text-muted">
                {{ __('body.no_bookings_found') }}
            </div>
        @else
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                            <th class="px-5 py-3 font-semibold">{{ __('body.booking_number') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.pickup_datetime') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.total_amount') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('body.view') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($bookings as $booking)
                            <tr class="hover:bg-surface">
                                <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                                    <a href="{{ route('account.bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->booking_number }}</a>
                                </td>
                                <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name }}</td>
                                <td class="px-5 py-3.5 text-muted whitespace-nowrap">@kigali($booking->pickup_at)</td>
                                <td class="px-5 py-3.5 text-ink whitespace-nowrap">{{ number_format($booking->total_amount, 0) }} RWF</td>
                                <td class="px-5 py-3.5"><x-common.status :status="$booking->status->label()" /></td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <a href="{{ route('account.bookings.show', $booking) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $bookings->links() }}
            </div>
        @endif
</x-layouts.public>
