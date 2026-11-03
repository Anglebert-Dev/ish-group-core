@php use App\Helpers\AuthHelper; @endphp
<nav class="flex flex-wrap gap-2 mb-4">
    @if (AuthHelper::can('list injongepay_connect.connection'))
        <a href="{{ route('injongepayconnect.connections.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('injongepayconnect.connections.*') ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ __('injongepayconnect::connect.connected_systems') }}</a>
    @endif
    @if (AuthHelper::can('list injongepay_connect.invoice'))
        <a href="{{ route('injongepayconnect.invoices') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('injongepayconnect.invoices') ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ __('injongepayconnect::connect.linked_invoices') }}</a>
    @endif
</nav>
