@php
    $tabs = [
        ['label' => __('distribution::distribution.sites'), 'route' => 'superadmin.distribution.sites.index', 'active' => 'superadmin.distribution.sites.*'],
        ['label' => __('distribution::distribution.releases'), 'route' => 'superadmin.distribution.releases.index', 'active' => 'superadmin.distribution.releases.*'],
    ];
@endphp

<x-superadmin.nav />

<nav class="flex flex-wrap gap-1.5 mb-6">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['active']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium border transition-colors {{ $active ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line hover:border-brand hover:text-brand' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
