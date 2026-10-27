@php $impersonation = session(\App\Services\SuperAdmin\ImpersonationService::SESSION_KEY); @endphp

@if ($impersonation)
    <div class="sticky top-0 z-30 bg-amber-500 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 sm:px-5 lg:px-8 py-2 text-sm">
            <p class="font-medium">
                {{ __('body.impersonation_banner', [
                    'name' => auth('staff')->user()?->name,
                    'business' => auth('staff')->user()?->business?->name,
                    'admin' => $impersonation['impersonator_name'],
                ]) }}
            </p>
            <form action="{{ route('impersonation.leave') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-lg bg-white/20 hover:bg-white/30 px-3 py-1.5 font-semibold transition-colors">
                    {{ __('body.impersonation_return') }}
                </button>
            </form>
        </div>
    </div>
@endif
