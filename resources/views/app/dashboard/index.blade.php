@php
    $fleetTotal = array_sum(array_column($fleetStatus, 'count'));
    $volumeCounts = array_column($bookingVolume, 'count');
    $volumeMax = max(1, ...($volumeCounts ?: [0]));
    $canBookings = \App\Helpers\AuthHelper::can('list bookings.booking');
    $canRentals = \App\Helpers\AuthHelper::can('list operations.rental');
    $canVehicles = \App\Helpers\AuthHelper::can('list fleet.vehicle');
    $canMaintenance = \App\Helpers\AuthHelper::can('list fleet.vehicle_maintenance');
    $canInvoices = \App\Helpers\AuthHelper::can('list finance.invoice');
    $canCreateVehicle = \App\Helpers\AuthHelper::can('create fleet.vehicle');

    $relativeDay = function ($date) {
        if (! $date) {
            return '—';
        }
        if ($date->isToday()) {
            return __('body.today');
        }
        if ($date->isTomorrow()) {
            return __('body.tomorrow');
        }

        return $date->format('d M');
    };

    $calendarIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
    $carIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"/></svg>';
    $moneyIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    $wrenchIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>';
    $clockIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    $docIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    $checkIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
@endphp

<x-layouts.app :title="__('body.dashboard')">

    <x-common.page-header :title="__('body.dashboard')" :description="__('body.dashboard_description')">
        <x-slot:actions>
            @if ($canBookings)
                <x-common.button href="{{ route('bookings.index') }}" variant="secondary" size="sm">{{ __('body.view_bookings') }}</x-common.button>
            @endif
            @if ($canCreateVehicle)
                <x-common.button href="{{ route('fleet.vehicles.create') }}" size="sm">+ {{ __('body.add_vehicle') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="space-y-6">
        <x-dashboard.quick-actions />

        <x-dashboard.attention-card
            :title="__('body.upcoming_pickups')"
            :href="$canBookings ? route('bookings.index') : null"
            tone="info"
        >
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </x-slot:icon>
            @if ($upcomingPickups->isEmpty())
                <p class="text-sm text-muted py-2">{{ __('body.no_upcoming_pickups') }}</p>
            @else
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full min-w-[480px] text-sm text-left">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-muted">
                                <th class="py-1.5 pr-3 font-semibold">{{ __('body.customer') }}</th>
                                <th class="py-1.5 px-2 font-semibold">{{ __('body.vehicles') }}</th>
                                <th class="py-1.5 px-2 font-semibold text-right">{{ __('body.pickup_datetime') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($upcomingPickups as $booking)
                                <tr class="border-t border-blue-100/80 hover:bg-blue-100/50">
                                    <td class="py-2 pr-3 font-medium text-ink max-w-[200px] truncate">
                                        <a href="{{ route('bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->customerName() }}</a>
                                    </td>
                                    <td class="py-2 px-2 text-body whitespace-nowrap">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name }}</td>
                                    <td class="py-2 px-2 text-right tabular-nums font-medium text-blue-600 whitespace-nowrap">{{ $relativeDay($booking->pickup_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dashboard.attention-card>

        @if ($overdueReturns->isNotEmpty())
            <x-dashboard.attention-card
                :title="__('body.overdue_returns')"
                :href="$canRentals ? route('rentals.index') : null"
                :link-label="__('body.view_all_count', ['count' => $stats['overdue_returns']])"
                tone="danger"
            >
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full min-w-[480px] text-sm text-left">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-muted">
                                <th class="py-1.5 pr-3 font-semibold">{{ __('body.customer') }}</th>
                                <th class="py-1.5 px-2 font-semibold">{{ __('body.vehicles') }}</th>
                                <th class="py-1.5 px-2 font-semibold text-right">{{ __('body.return_datetime') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overdueReturns as $booking)
                                <tr class="border-t border-red-100/80 hover:bg-red-100/50">
                                    <td class="py-2 pr-3 font-medium text-ink max-w-[200px] truncate">
                                        <a href="{{ $canRentals ? route('rentals.show', $booking) : route('bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->customerName() }}</a>
                                    </td>
                                    <td class="py-2 px-2 text-body whitespace-nowrap">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name }}</td>
                                    <td class="py-2 px-2 text-right tabular-nums font-medium text-red-600 whitespace-nowrap">{{ $relativeDay($booking->return_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.attention-card>
        @endif

        @if ($complianceAlerts->isNotEmpty())
            @php
                $hasRedCompliance = $complianceAlerts->contains(fn ($alert) => $alert['level'] === \App\Enums\DocumentAlertLevel::Red);
            @endphp
            <x-dashboard.attention-card
                :title="__('body.document_expiry_alerts')"
                tone="{{ $hasRedCompliance ? 'danger' : 'warning' }}"
            >
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full min-w-[520px] text-sm text-left">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-muted">
                                <th class="py-1.5 pr-3 font-semibold">{{ __('body.subject') }}</th>
                                <th class="py-1.5 px-2 font-semibold">{{ __('body.document') }}</th>
                                <th class="py-1.5 px-2 font-semibold">{{ __('body.status') }}</th>
                                <th class="py-1.5 px-2 font-semibold text-right">{{ __('body.expires') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($complianceAlerts as $alert)
                                <tr class="border-t {{ $hasRedCompliance ? 'border-red-100/80 hover:bg-red-100/50' : 'border-amber-100/80 hover:bg-amber-100/50' }}">
                                    <td class="py-2 pr-3 font-medium text-ink max-w-[220px] truncate">
                                        <a href="{{ $alert['href'] }}" class="hover:text-brand">{{ $alert['subject'] }}</a>
                                    </td>
                                    <td class="py-2 px-2 text-body whitespace-nowrap">{{ $alert['document'] }}</td>
                                    <td class="py-2 px-2 whitespace-nowrap">
                                        <x-common.badge :color="$alert['level']->tone()">{{ $alert['summary'] }}</x-common.badge>
                                    </td>
                                    <td class="py-2 px-2 text-right tabular-nums font-medium whitespace-nowrap {{ $alert['level'] === \App\Enums\DocumentAlertLevel::Red ? 'text-red-600' : 'text-amber-700' }}">
                                        {{ $alert['expires_at']?->format('d M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.attention-card>
        @endif

        @if ($pendingBookings->isNotEmpty())
            <x-dashboard.attention-card
                :title="__('body.pending_confirmations')"
                :href="$canBookings ? route('bookings.index') : null"
                :link-label="__('body.view_all_count', ['count' => $stats['pending_bookings']])"
                tone="warning"
            >
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full min-w-[480px] text-sm text-left">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-muted">
                                <th class="py-1.5 pr-3 font-semibold">{{ __('body.booking_number') }}</th>
                                <th class="py-1.5 px-2 font-semibold">{{ __('body.customer') }}</th>
                                <th class="py-1.5 px-2 font-semibold text-right">{{ __('body.pickup_datetime') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingBookings as $booking)
                                <tr class="border-t border-amber-100/80 hover:bg-amber-100/50">
                                    <td class="py-2 pr-3 font-medium text-ink whitespace-nowrap">
                                        <a href="{{ route('bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->booking_number }}</a>
                                    </td>
                                    <td class="py-2 px-2 text-body whitespace-nowrap">{{ $booking->customerName() }}</td>
                                    <td class="py-2 px-2 text-right tabular-nums font-medium text-amber-700 whitespace-nowrap">{{ $relativeDay($booking->pickup_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.attention-card>
        @endif

        <x-dashboard.kpi-group :title="__('body.operations')">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </x-slot:icon>
            <x-dashboard.stat-card
                :label="__('body.active_bookings')"
                :value="$stats['active_bookings']"
                :href="$canBookings ? route('bookings.index') : null"
                :description="__('body.active_bookings_hint')"
                :icon="$calendarIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.pending_bookings')"
                :value="$stats['pending_bookings']"
                :href="$canBookings ? route('bookings.index') : null"
                :description="__('body.pending_bookings_hint')"
                :icon="$clockIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.ready_for_pickup')"
                :value="$stats['ready_for_pickup']"
                :href="$canRentals ? route('rentals.index') : null"
                :description="__('body.ready_for_pickup_hint')"
                :icon="$checkIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.vehicles_on_hire')"
                :value="$stats['vehicles_on_hire']"
                :href="$canRentals ? route('rentals.index') : null"
                :description="__('body.vehicles_on_hire_hint')"
                :icon="$carIcon"
            />
        </x-dashboard.kpi-group>

        <x-dashboard.kpi-group :title="__('body.fleet_and_finance')">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
            <x-dashboard.stat-card
                :label="__('body.vehicles_available')"
                :value="$stats['vehicles_available']"
                :href="$canVehicles ? route('fleet.vehicles.index') : null"
                :description="__('body.vehicles_available_hint')"
                :icon="$carIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.vehicles_in_maintenance')"
                :value="$stats['vehicles_in_maintenance']"
                :href="$canMaintenance ? route('fleet.maintenance.index') : null"
                :description="__('body.vehicles_in_maintenance_hint')"
                :icon="$wrenchIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.revenue_this_month')"
                :value="number_format($stats['revenue_this_month'], 0).' RWF'"
                :href="$canInvoices ? route('finance.invoices.index') : null"
                :description="__('body.revenue_this_month_hint')"
                :icon="$moneyIcon"
            />
            <x-dashboard.stat-card
                :label="__('body.unpaid_invoices')"
                :value="$stats['unpaid_invoices']"
                :href="$canInvoices ? route('finance.invoices.index') : null"
                :description="__('body.unpaid_invoices_hint')"
                :icon="$docIcon"
            />
        </x-dashboard.kpi-group>

        <div class="bg-white border border-line rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1.5 h-6 rounded-full bg-brand"></div>
                <h2 class="text-base font-semibold text-ink">{{ __('body.bookings_this_week') }}</h2>
            </div>
            <div class="flex items-end gap-2 sm:gap-3 h-32">
                @foreach ($bookingVolume as $day)
                    <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                        <span class="text-xs font-semibold text-ink tabular-nums">{{ $day['count'] }}</span>
                        <div class="w-full rounded-t-md bg-brand/15 overflow-hidden flex items-end" style="height: 100%">
                            <div class="w-full rounded-t-md bg-brand" style="height: {{ round($day['count'] / $volumeMax * 100) }}%"></div>
                        </div>
                        <span class="text-[11px] text-muted">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 relative bg-white border border-line rounded-xl overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand"></div>
                <div class="flex items-center justify-between px-4 sm:px-5 py-4 border-b border-line gap-3">
                    <p class="font-semibold text-ink">{{ __('body.recent_bookings') }}</p>
                    @if ($canBookings)
                        <a href="{{ route('bookings.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                <th class="px-5 py-3 font-semibold">{{ __('body.booking_number') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.customer') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.vehicles') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.pickup_datetime') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($recentBookings as $booking)
                                <tr class="hover:bg-surface">
                                    <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                                        <a href="{{ route('bookings.show', $booking) }}" class="hover:text-brand">{{ $booking->booking_number }}</a>
                                    </td>
                                    <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ $booking->customerName() }}</td>
                                    <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ $booking->assignedVehicle?->name() ?? $booking->category?->name }}</td>
                                    <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $booking->pickup_at->format('d M') }} – {{ $booking->return_at->format('d M') }}</td>
                                    <td class="px-5 py-3.5 text-body whitespace-nowrap">{{ number_format($booking->total_amount, 0) }} RWF</td>
                                    <td class="px-5 py-3.5"><x-common.status :status="$booking->status->label()" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_bookings_found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="relative bg-white border border-line rounded-xl p-5">
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand rounded-l-xl"></div>
                <div class="flex items-center justify-between gap-3 mb-5">
                    <p class="font-semibold text-ink">{{ __('body.fleet_status') }}</p>
                    @if ($canVehicles)
                        <a href="{{ route('fleet.vehicles.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
                    @endif
                </div>
                <div class="space-y-4">
                    @forelse ($fleetStatus as $status)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1.5">
                                <span class="text-body">{{ $status['label'] }}</span>
                                <span class="font-semibold text-ink">{{ $status['count'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-surface overflow-hidden">
                                <div class="h-full rounded-full {{ $status['color'] === 'success' ? 'bg-success' : ($status['color'] === 'warning' ? 'bg-warning' : ($status['color'] === 'muted' ? 'bg-muted' : 'bg-brand')) }}"
                                     style="width: {{ $fleetTotal > 0 ? round($status['count'] / $fleetTotal * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ __('body.no_vehicles_yet') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</x-layouts.app>
