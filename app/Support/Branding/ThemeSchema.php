<?php

namespace App\Support\Branding;

use App\Libraries\ColorLibrary;

/**
 * What a theme document may hold (docs/branding.md §2.1). Anything else is
 * dropped, so a stored theme can never carry a bad value onto a page.
 * Later phases add sections here (mode, layout, typography…).
 */
final class ThemeSchema
{
    /** Colours a business may set; brand itself stays on Look (it is identity). */
    public const COLORS = [
        'accent', 'link', 'page', 'card', 'surface', 'line', 'ink', 'body', 'muted',
        'success', 'warning', 'danger', 'info', 'sidebar', 'sidebar_ink', 'header', 'header_ink',
        'chart_1', 'chart_2', 'chart_3', 'chart_4', 'chart_5', 'chart_6',
    ];

    public const DEFAULT_MODE = 'light';

    /**
     * Layout & typography (docs/branding.md §3.4–3.5). The first value of each
     * is the default: today's look.
     */
    public const LAYOUT = [
        'sidebar_width' => ['standard', 'compact', 'wide'],
        'sidebar_style' => ['solid', 'minimal', 'floating'],
        'sidebar_start' => ['expanded', 'collapsed'],
        'header' => ['standard', 'compact'],
        'density' => ['comfortable', 'compact'],
        'content_width' => ['full', 'wide', 'standard'],
        'cards' => ['bordered', 'flat', 'elevated'],
        'corners' => ['rounded', 'slight', 'sharp'],
        'buttons' => ['rounded', 'square', 'pill'],
        'font_size' => ['normal', 'small', 'large'],
        'line_height' => ['normal', 'relaxed'],
    ];

    /** Sections the platform's theme passes on to every business (not dashboard, menu…). */
    public const SHARED = ['colors', 'colors_dark', 'mode', 'user_mode', 'layout', 'heading_font', 'switches'];

    /** Parts a preset sets: appearance only, never content. */
    public const PRESET_SECTIONS = ['colors', 'colors_dark', 'mode', 'layout', 'heading_font', 'switches'];

    /** Ready-made looks (docs/branding.md §3.14): appearance only. */
    public const PRESETS = [
        'corporate' => ['colors' => ['page' => '#F4F6F9', 'sidebar' => '#0F2744', 'sidebar_ink' => '#E2E8F0'], 'layout' => ['corners' => 'slight', 'cards' => 'bordered']],
        'minimal' => ['layout' => ['sidebar_style' => 'minimal', 'cards' => 'flat', 'header' => 'compact']],
        'modern' => ['colors' => ['page' => '#F4F5FA'], 'layout' => ['sidebar_style' => 'floating', 'cards' => 'elevated', 'buttons' => 'pill'], 'heading_font' => 'poppins'],
        'dark' => ['mode' => 'dark', 'layout' => ['cards' => 'bordered']],
        'warm' => ['colors' => ['page' => '#FAF7F2', 'surface' => '#F5EFE6', 'line' => '#E7DFD3', 'sidebar' => '#FFFDF9', 'ink' => '#1C1917', 'body' => '#292524', 'muted' => '#78716C'], 'heading_font' => 'merriweather'],
        'fashion' => ['colors' => ['page' => '#FBF7F4', 'sidebar' => '#2B1B17', 'sidebar_ink' => '#F5E9E2'], 'layout' => ['cards' => 'elevated', 'buttons' => 'pill'], 'heading_font' => 'playfair'],
        'retail' => ['colors' => ['page' => '#F6F8F6'], 'layout' => ['density' => 'compact', 'cards' => 'bordered'], 'heading_font' => 'dm_sans'],
        'services' => ['colors' => ['page' => '#F7F9FC', 'sidebar' => '#1E293B', 'sidebar_ink' => '#E2E8F0'], 'layout' => ['corners' => 'slight'], 'heading_font' => 'source_serif'],
    ];

    public const MAX_SAVED_PRESETS = 12;

    public const BANNERS = ['none', 'logo', 'image'];

    public const TONES = ['info', 'success', 'warning', 'danger'];

    /** Menu icons a business may pick (outline icons, 24px grid). */
    public const ICONS = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z',
        'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'cart' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'cash' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        'card' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'tag' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'truck' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'scissors' => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z',
        'briefcase' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'archive' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'chat' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        'bell' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        'globe' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
        'swap' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4-4m-4 4l4 4',
        'settings' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
    ];

    /** On/off parts of the interface, with their defaults. */
    public const SWITCHES = [
        'page_subtitles' => true,
        'back_to_top' => true,
        'motion' => true,
        'loading_bar' => true,
    ];

    /**
     * Ready-made colour sets for the Theme tab (light colours; blank = default).
     * Applying one fills the form; nothing is saved until "Save draft".
     */
    public const PALETTES = [
        'default' => [],
        'navy' => ['page' => '#F3F5F9', 'sidebar' => '#0B1F3A', 'sidebar_ink' => '#E2E8F0'],
        'charcoal' => ['page' => '#F5F5F4', 'sidebar' => '#1F2937', 'sidebar_ink' => '#F3F4F6', 'line' => '#E7E5E4'],
        'warm' => ['page' => '#FAF7F2', 'surface' => '#F5EFE6', 'line' => '#E7DFD3', 'sidebar' => '#FFFDF9', 'ink' => '#1C1917', 'body' => '#292524', 'muted' => '#78716C'],
        'forest' => ['page' => '#F4F7F5', 'sidebar' => '#0F2A1D', 'sidebar_ink' => '#E3F1E8', 'line' => '#DDE6E0'],
        'slate' => ['page' => '#EEF2F6', 'card' => '#FFFFFF', 'surface' => '#F1F5F9', 'line' => '#D9E1EA', 'header' => '#F8FAFC', 'sidebar' => '#F8FAFC'],
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    private static function cleanDashboard(mixed $given): array
    {
        if (! is_array($given)) {
            return [];
        }
        $text = fn ($value, int $max) => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
        $key = fn ($value) => is_string($value) && preg_match('/^[A-Za-z0-9_:.-]{1,80}$/', $value);

        return array_filter([
            'welcome_title' => $text($given['welcome_title'] ?? null, 120),
            'welcome_text' => $text($given['welcome_text'] ?? null, 500),
            'banner' => in_array($given['banner'] ?? null, self::BANNERS, true) ? $given['banner'] : null,
            'stat_columns' => in_array((int) ($given['stat_columns'] ?? 0), [2, 3, 4], true) ? (int) $given['stat_columns'] : null,
            // Blocks in order, and which are hidden.
            'order' => array_values(array_unique(array_filter((array) ($given['order'] ?? []), $key))) ?: null,
            'hidden' => array_values(array_unique(array_filter((array) ($given['hidden'] ?? []), $key))) ?: null,
            // Block => roles that don't see it (hiding only: permissions still apply).
            'roles_hidden' => collect((array) ($given['roles_hidden'] ?? []))
                ->filter(fn ($roles, $block) => $key($block) && is_array($roles))
                ->map(fn ($roles) => array_values(array_unique(array_map('intval', array_filter($roles, 'is_numeric')))))
                ->filter()
                ->all() ?: null,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private static function cleanMenu(mixed $given): array
    {
        if (! is_array($given)) {
            return [];
        }
        $text = fn ($value) => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 60) : null;
        $order = fn ($value) => is_numeric($value) ? max(-999, min(999, (int) $value)) : null;

        $items = collect((array) ($given['items'] ?? []))
            ->filter(fn ($item, $route) => is_string($route) && preg_match('/^[a-z0-9_.-]{1,100}$/i', $route) && is_array($item))
            ->map(fn ($item) => array_filter([
                'label' => $text($item['label'] ?? null),
                'icon' => array_key_exists($item['icon'] ?? '', self::ICONS) ? $item['icon'] : null,
                'group' => $text($item['group'] ?? null),
                'order' => $order($item['order'] ?? null),
                'hidden' => ! empty($item['hidden']) ? true : null,
            ], fn ($value) => $value !== null))
            ->filter()
            ->all();

        $groups = collect((array) ($given['groups'] ?? []))
            ->filter(fn ($group, $name) => is_string($name) && mb_strlen($name) <= 60 && is_array($group))
            ->map(fn ($group) => array_filter(['label' => $text($group['label'] ?? null), 'order' => $order($group['order'] ?? null)], fn ($value) => $value !== null))
            ->filter()
            ->all();

        return array_filter(['items' => $items, 'groups' => $groups]);
    }

    /** @return array<string, mixed> */
    private static function cleanAnnouncement(mixed $given): array
    {
        $text = is_array($given) && is_string($given['text'] ?? null) ? mb_substr(trim($given['text']), 0, 500) : '';
        if ($text === '') {
            return [];
        }
        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;

        return array_filter([
            'text' => $text,
            'tone' => in_array($given['tone'] ?? null, self::TONES, true) ? $given['tone'] : 'info',
            'from' => $date($given['from'] ?? null),
            'until' => $date($given['until'] ?? null),
        ]);
    }

    public static function clean(array $payload): array
    {
        $colors = fn (mixed $given) => collect(self::COLORS)
            ->mapWithKeys(fn ($key) => [$key => is_array($given) ? ColorLibrary::parse(is_string($given[$key] ?? null) ? $given[$key] : null) : null])
            ->filter()
            ->all();

        return array_filter([
            'colors' => $colors($payload['colors'] ?? null),
            'colors_dark' => $colors($payload['colors_dark'] ?? null) + array_filter(['brand' => ColorLibrary::parse(is_string($payload['colors_dark']['brand'] ?? null) ? $payload['colors_dark']['brand'] : null)]),
            'layout' => collect(self::LAYOUT)
                ->mapWithKeys(fn ($allowed, $key) => [$key => in_array($payload['layout'][$key] ?? null, $allowed, true) ? $payload['layout'][$key] : null])
                ->filter()
                ->all(),
            // "same" = the system font.
            'heading_font' => is_string($payload['heading_font'] ?? null) && array_key_exists($payload['heading_font'], Fonts::LIST) ? $payload['heading_font'] : null,
            'switches' => collect(self::SWITCHES)
                ->mapWithKeys(fn ($default, $key) => [$key => array_key_exists($key, (array) ($payload['switches'] ?? [])) ? (bool) $payload['switches'][$key] : null])
                ->filter(fn ($value) => $value !== null)
                ->all(),
            'dashboard' => self::cleanDashboard($payload['dashboard'] ?? null),
            'menu' => self::cleanMenu($payload['menu'] ?? null),
            'landing' => is_string($payload['landing'] ?? null) && preg_match('/^[a-z0-9_.-]{1,100}$/i', $payload['landing']) ? $payload['landing'] : null,
            'announcement' => self::cleanAnnouncement($payload['announcement'] ?? null),
            // Light, dark, or follow each device.
            'mode' => in_array($payload['mode'] ?? null, ThemeTokens::MODES, true) ? $payload['mode'] : null,
            // May each person pick their own light/dark? (Unset = yes.)
            'user_mode' => array_key_exists('user_mode', $payload) ? (bool) $payload['user_mode'] : null,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
