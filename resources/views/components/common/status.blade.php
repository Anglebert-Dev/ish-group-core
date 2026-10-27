@props(['status'])

@php
    $map = [
        'Available'   => 'success',
        'Reserved'    => 'brand',
        'On Hire'     => 'brand',
        'Maintenance' => 'warning',
        'Cancelled'   => 'danger',
        'Completed'   => 'success',
        'Pending'     => 'warning',
        'Active'      => 'success',
        'Inactive'    => 'muted',
        'Banned'      => 'danger',
        'On Job'      => 'brand',
        'Off Duty'    => 'muted',
        'Scheduled'   => 'warning',
        'In Progress' => 'brand',
        'Confirmed'   => 'brand',
        'Ready for Pickup' => 'brand',
        'Active Rental' => 'success',
        'No Show'     => 'danger',
        'Draft'       => 'muted',
        'Created'     => 'success',
        'Updated'     => 'brand',
        'Deleted'     => 'danger',
    ];
    $color = $map[$status] ?? 'muted';
@endphp

<x-common.badge :color="$color">{{ $status }}</x-common.badge>
