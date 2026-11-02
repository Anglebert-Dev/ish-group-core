<?php

namespace App\Support\Printing;

use App\Models\Business\Business;
use App\Services\Branding\BrandingService;
use App\Support\Branding\Brand;
use App\Support\Settings\BusinessSettings;

/**
 * How a business's documents look (docs/branding-print-build-guide.md §4).
 * Saved as "print.*" business settings; every value has a default taken
 * from the brand, so documents look right before anyone opens the designer.
 * $overrides lets the designer preview unsaved choices.
 */
final class PrintDesign
{
    public const LAYOUTS = ['classic', 'modern', 'compact'];

    public const PAPERS = ['a4' => 'A4', 'letter' => 'Letter', 'a5' => 'A5'];

    public const LOGO_POSITIONS = ['left', 'center', 'right'];

    public const LOGO_SIZES = ['small' => 34, 'medium' => 50, 'large' => 70];

    /** Toggles and their defaults. */
    public const SWITCHES = [
        'show_contact' => true,
        'show_watermark' => true,
        'show_payment' => true,
        'show_signature' => true,
        'show_stamp' => true,
    ];

    /**
     * @param  array<string, array{terms?: ?string, footer?: ?string}>  $types
     */
    public function __construct(
        public readonly string $layout,
        public readonly string $paper,
        public readonly string $accent,
        public readonly string $font,
        public readonly string $logoPosition,
        public readonly string $logoSize,
        public readonly ?string $headerNote,
        public readonly ?string $footerText,
        public readonly bool $showContact,
        public readonly bool $showWatermark,
        public readonly bool $showPayment,
        public readonly bool $showSignature,
        public readonly bool $showStamp,
        public readonly array $types,
    ) {
    }

    public static function for(Business $business, ?Brand $brand = null, array $overrides = []): self
    {
        $brand ??= app(BrandingService::class)->brand($business);
        $settings = app(BusinessSettings::class);
        $get = fn (string $key, mixed $default = null) => array_key_exists($key, $overrides) ? $overrides[$key] : $settings->get('print.'.$key, $default, $business);
        $pick = fn (string $key, array $allowed, string $default) => in_array($value = (string) $get($key, $default), $allowed, true) ? $value : $default;
        $flag = fn (string $key) => filter_var($get($key, self::SWITCHES[$key]), FILTER_VALIDATE_BOOLEAN);
        $accent = strtoupper((string) $get('accent', $brand->primaryColor));

        return new self(
            layout: $pick('layout', self::LAYOUTS, 'classic'),
            paper: $pick('paper', array_keys(self::PAPERS), 'a4'),
            accent: preg_match('/^#[0-9A-F]{6}$/', $accent) ? $accent : $brand->primaryColor,
            font: $pick('font', array_keys(BrandingService::FONTS), $brand->font),
            logoPosition: $pick('logo_position', self::LOGO_POSITIONS, 'left'),
            logoSize: $pick('logo_size', array_keys(self::LOGO_SIZES), 'medium'),
            headerNote: self::text($get('header_note')),
            footerText: self::text($get('footer_text')),
            showContact: $flag('show_contact'),
            showWatermark: $flag('show_watermark'),
            showPayment: $flag('show_payment'),
            showSignature: $flag('show_signature'),
            showStamp: $flag('show_stamp'),
            types: is_array($types = $get('types', [])) ? $types : [],
        );
    }

    /** Terms printed on this kind of document (e.g. payment terms on invoices). */
    public function termsFor(string $type): ?string
    {
        return self::text($this->types[$type]['terms'] ?? null);
    }

    /** The document type's own footer, else the shared one. */
    public function footerFor(string $type): ?string
    {
        return self::text($this->types[$type]['footer'] ?? null) ?? $this->footerText;
    }

    public function logoHeight(): int
    {
        return self::LOGO_SIZES[$this->logoSize];
    }

    /** CSS font stack: dompdf uses the first, browsers fall back sensibly. */
    public function fontStack(): string
    {
        return match ($this->font) {
            'serif' => '"DejaVu Serif", Georgia, "Times New Roman", serif',
            'helvetica' => 'Helvetica, Arial, sans-serif',
            'times' => 'Times, "Times New Roman", serif',
            default => '"DejaVu Sans", "Segoe UI", Arial, sans-serif',
        };
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
