{{-- Tailwind with the design tokens (docs/branding.md §2.3): every layout uses this, so colour names mean the same everywhere. --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = @json(\App\Support\Branding\ThemeTokens::tailwind(), JSON_UNESCAPED_SLASHES);</script>
