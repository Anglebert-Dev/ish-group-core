<?php

namespace App\Support\Printing;

use App\Models\Business\Business;
use App\Services\Branding\BrandingService;
use App\Support\Branding\Brand;
use App\Support\Branding\Fonts;
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

    /** Page background: plain white, or a faint tint of the accent colour. */
    public const BACKGROUNDS = ['none', 'tint'];

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
        public readonly ?string $watermarkText = null,
        public readonly string $background = 'none',
    ) {}

    /**
     * The design for one kind of document: its own layout when chosen
     * (e.g. Modern for quotations, Compact for receipts).
     */
    public function forType(string $type): self
    {
        $layout = $this->types[$type]['layout'] ?? null;
        if (! in_array($layout, self::LAYOUTS, true) || $layout === $this->layout) {
            return $this;
        }

        return new self($layout, $this->paper, $this->accent, $this->font, $this->logoPosition, $this->logoSize, $this->headerNote, $this->footerText,
            $this->showContact, $this->showWatermark, $this->showPayment, $this->showSignature, $this->showStamp, $this->types, $this->watermarkText, $this->background);
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
            // Blank (or a print-only choice saved before B0) = the system font.
            font: array_key_exists((string) $get('font'), Fonts::LIST) ? (string) $get('font') : $brand->font,
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
            // E.g. "COPY": only on documents without a status mark (PAID, CANCELLED… always win).
            watermarkText: self::text(mb_substr((string) $get('watermark_text'), 0, 30)),
            background: $pick('background', self::BACKGROUNDS, 'none'),
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

    /** CSS font stack: the bundled font for dompdf (pdf) or the browser (html). */
    public function fontStack(string $mode = 'pdf'): string
    {
        return $mode === 'pdf' ? Fonts::pdfStack($this->font) : Fonts::stack($this->font);
    }

    /** The @font-face rules that go with fontStack(). */
    public function fontFaces(string $mode = 'pdf'): string
    {
        return $mode === 'pdf' ? Fonts::pdfFaceCss($this->font) : Fonts::faceCss($this->font);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
