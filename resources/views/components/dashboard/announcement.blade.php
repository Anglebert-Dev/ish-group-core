{{-- The business's notice to its staff (Branding → Dashboard), between its dates; each person can close it (kept in their browser). --}}
@php
    $note = isset($branding) && $branding->exists ? (app(\App\Services\Branding\BrandingService::class)->theme($branding)['announcement'] ?? null) : null;
    $live = $note
        && (empty($note['from']) || ! \Illuminate\Support\Carbon::parse($note['from'])->isFuture())
        && (empty($note['until']) || ! \Illuminate\Support\Carbon::parse($note['until'])->endOfDay()->isPast());
    $tone = $note['tone'] ?? 'info';
    $styles = ['info' => 'border-info/30 bg-info/10', 'success' => 'border-success/30 bg-success/10', 'warning' => 'border-warning/30 bg-warning/10', 'danger' => 'border-danger/30 bg-danger/10'];
@endphp
@if ($live)
    <div id="announcement" data-key="announcement-{{ substr(md5($note['text']), 0, 12) }}" class="hidden border-b {{ $styles[$tone] ?? $styles['info'] }}">
        <div class="flex items-start justify-between gap-3 px-4 sm:px-5 lg:px-8 py-2.5 text-sm text-ink">
            <p class="min-w-0">{!! nl2br(e($note['text'])) !!}</p>
            <button type="button" class="shrink-0 text-muted hover:text-ink" aria-label="{{ __('body.close') }}"
                    onclick="var a = document.getElementById('announcement'); a.classList.add('hidden'); try { localStorage.setItem(a.dataset.key, '1'); } catch (e) {}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    <script>(function () { var a = document.getElementById('announcement'); try { if (localStorage.getItem(a.dataset.key) === '1') return; } catch (e) {} a.classList.remove('hidden'); })();</script>
@endif
