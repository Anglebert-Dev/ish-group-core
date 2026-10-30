{{-- @include('savings::partials.money', ['v' => 1250, 'c' => 'USD', 'signed' => true]) --}}
@php $signed ??= false; $cls = $signed ? ((int) $v < 0 ? 'text-danger' : ((int) $v > 0 ? 'text-success' : '')) : ''; @endphp<span class="{{ $cls }} whitespace-nowrap">{{ $signed && (int) $v > 0 ? '+' : '' }}{{ \Modules\Savings\Support\Money::format((int) $v, $c ?? null) }}</span>
