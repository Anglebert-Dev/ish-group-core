@props(['text'])

{{-- Plain text with blank lines between paragraphs (no HTML from editors). --}}
@foreach (preg_split('/\R{2,}/', trim((string) $text)) as $paragraph)
    @if (filled($paragraph))
        <p {{ $attributes }}>{!! nl2br(e($paragraph)) !!}</p>
    @endif
@endforeach
