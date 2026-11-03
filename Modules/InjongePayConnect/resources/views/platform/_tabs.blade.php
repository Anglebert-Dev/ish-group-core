@php use App\Helpers\AuthHelper; @endphp
<nav class="flex flex-wrap gap-2 mb-4">
    @foreach ([
        ['integrators', 'list injongepay_connect_platform.integrator'],
        ['calls', 'list injongepay_connect_platform.call'],
    ] as [$tab, $permission])
        @if (AuthHelper::can($permission))
            <a href="{{ route('injongepayconnect.platform.'.$tab.'.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('injongepayconnect.platform.'.$tab.'.*') ? 'bg-brand text-on-brand' : 'bg-card border border-line text-body' }}">{{ __('injongepayconnect::connect.tab_'.$tab) }}</a>
        @endif
    @endforeach
</nav>
