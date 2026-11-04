{{-- Tailwind with the design tokens (docs/branding.md §2.3): every layout uses this, so colour names mean the same everywhere. --}}
{{-- A local copy, so pages keep their look on a server with no internet. --}}
<script src="{{ asset('js/tailwind-3.4.17.js') }}"></script>
<script>tailwind.config = @json(\App\Support\Branding\ThemeTokens::tailwind(), JSON_UNESCAPED_SLASHES);</script>
