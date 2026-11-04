@if ($module['active'])
    <x-common.badge color="success">{{ __('body.active') }}</x-common.badge>
@elseif ($module['installed'])
    <x-common.badge color="warning">{{ __('body.disabled') }}</x-common.badge>
@else
    <x-common.badge color="muted">{{ __('body.not_installed') }}</x-common.badge>
@endif
