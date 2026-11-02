<?php

namespace App\Support\Branding;

/**
 * Layout & typography choices as CSS (docs/branding.md §3.4–3.5): variables
 * the views already use (sidebar width, header height, radius, content
 * width…) and data- attributes on <html> that a few central rules read.
 * No view has to change for a choice to reach it.
 */
final class ThemeLayout
{
    private const SIDEBAR_WIDTH = ['compact' => '14rem', 'standard' => '16rem', 'wide' => '18rem'];

    private const HEADER = ['standard' => '4rem', 'compact' => '3.25rem'];

    private const CONTENT = ['full' => 'none', 'wide' => '100rem', 'standard' => '80rem'];

    /** md, lg, xl, 2xl */
    private const CORNERS = [
        'rounded' => ['0.375rem', '0.5rem', '0.75rem', '1rem'],
        'slight' => ['0.25rem', '0.3125rem', '0.4375rem', '0.5rem'],
        'sharp' => ['0.125rem', '0.125rem', '0.1875rem', '0.25rem'],
    ];

    private const FONT_SIZE = ['small' => '15px', 'normal' => '16px', 'large' => '17px'];

    private const LINE_HEIGHT = ['normal' => '1.5', 'relaxed' => '1.7'];

    /** @param  array<string, mixed>  $theme  the effective theme document */
    public function __construct(private array $theme) {}

    public function get(string $key): string
    {
        return $this->theme['layout'][$key] ?? ThemeSchema::LAYOUT[$key][0];
    }

    public function on(string $switch): bool
    {
        return $this->theme['switches'][$switch] ?? ThemeSchema::SWITCHES[$switch];
    }

    public function headingFont(): ?string
    {
        return $this->theme['heading_font'] ?? null;
    }

    public function css(string $bodyFont): string
    {
        [$md, $lg, $xl, $xxl] = self::CORNERS[$this->get('corners')];
        $button = match ($this->get('buttons')) {
            'square' => $md,
            'pill' => '9999px',
            default => $lg,
        };
        $heading = $this->headingFont() && $this->headingFont() !== $bodyFont
            ? str_replace(Fonts::FAMILY, 'BrandHeading', Fonts::stack($this->headingFont()))
            : 'var(--font-body)';

        return ':root{'.implode(';', [
            '--sidebar-w-open:'.self::SIDEBAR_WIDTH[$this->get('sidebar_width')],
            '--header-h:'.self::HEADER[$this->get('header')],
            '--content-max:'.self::CONTENT[$this->get('content_width')],
            "--radius-md:{$md}", "--radius-lg:{$lg}", "--radius-xl:{$xl}", "--radius-2xl:{$xxl}",
            "--radius-button:{$button}",
            '--font-heading:'.$heading,
            '--line-height:'.self::LINE_HEIGHT[$this->get('line_height')],
        ]).'}html{font-size:'.self::FONT_SIZE[$this->get('font_size')].'}';
    }

    /** @font-face for a heading font that differs from the body font. */
    public function headingFaces(string $bodyFont): string
    {
        $font = $this->headingFont();

        return $font && $font !== $bodyFont ? str_replace('"'.Fonts::FAMILY.'"', '"BrandHeading"', Fonts::faceCss($font)) : '';
    }

    /** data- attributes for <html>. */
    public function attributes(): string
    {
        $attributes = [
            'data-density' => $this->get('density'),
            'data-cards' => $this->get('cards'),
            'data-sidebar' => $this->get('sidebar_style'),
            'data-sidebar-start' => $this->get('sidebar_start'),
            'data-motion' => $this->on('motion') ? 'on' : 'off',
            'data-subtitles' => $this->on('page_subtitles') ? 'on' : 'off',
        ];

        return collect($attributes)->map(fn ($value, $key) => $key.'="'.e($value).'"')->implode(' ');
    }
}
