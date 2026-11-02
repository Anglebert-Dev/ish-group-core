<?php

namespace App\Support\Entitlements;

/**
 * Extras a package can include or leave out (docs/branding.md §2.6), on top
 * of modules and limits. Without Billing every capability is available.
 * A capability not listed on a package made before it existed is allowed,
 * so adding one never takes anything away from current customers.
 */
final class Capabilities
{
    /** key => wording key */
    public const LIST = [
        'branding.custom_css' => 'body.capability_custom_css',
        'branding.fonts' => 'body.capability_fonts',
        'branding.terminology' => 'body.capability_terminology',
    ];

    /** @return array<string, string> key => label */
    public static function options(): array
    {
        return array_map(fn (string $label) => __($label), self::LIST);
    }
}
