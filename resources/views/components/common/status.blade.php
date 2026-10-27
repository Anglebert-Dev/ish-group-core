@props(['status'])

@php
    $map = [
        'Cancelled'   => 'danger',
        'Completed'   => 'success',
        'Pending'     => 'warning',
        'Active'      => 'success',
        'Inactive'    => 'muted',
        'Banned'      => 'danger',
        'Scheduled'   => 'warning',
        'In Progress' => 'brand',
        'Confirmed'   => 'brand',
        'Draft'       => 'muted',
        'Created'     => 'success',
        'Updated'     => 'brand',
        'Deleted'     => 'danger',
    ];
    $color = $map[$status] ?? 'muted';
@endphp

<x-common.badge :color="$color">{{ $status }}</x-common.badge>
