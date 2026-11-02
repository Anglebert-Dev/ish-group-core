@props(['font' => null])

{{-- The system font (docs/branding.md §3.5): only the chosen font's files, from the app itself. --}}
@php
    $font ??= isset($branding) ? app(\App\Services\Branding\BrandingService::class)->font($branding) : \App\Support\Branding\Fonts::DEFAULT;
@endphp
<style>
    {!! \App\Support\Branding\Fonts::faceCss($font) !!}
    :root { --font-body: {!! \App\Support\Branding\Fonts::stack($font) !!}; }
</style>
