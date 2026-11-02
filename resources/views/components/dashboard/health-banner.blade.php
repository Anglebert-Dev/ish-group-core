{{-- Superadmins: emails waiting or the scheduler stopped (App\Support\Health\BackgroundJobs). --}}
@if (auth('staff')->user()?->isSuperAdmin() && ($healthProblems = \App\Support\Health\BackgroundJobs::problems()))
    <div class="border-b border-warning/30 bg-warning/10">
        <div class="px-4 sm:px-5 lg:px-8 py-2.5 text-sm text-ink space-y-1.5">
            @foreach ($healthProblems as $problem)
                <p class="font-semibold">{{ $problem['title'] }}</p>
                <p class="text-body">{{ __('body.health_start') }} <code class="rounded bg-card border border-line px-1.5 py-0.5 text-xs select-all">{{ $problem['command'] }}</code></p>
            @endforeach
        </div>
    </div>
@endif
