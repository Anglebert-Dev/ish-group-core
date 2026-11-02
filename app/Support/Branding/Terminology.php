<?php

namespace App\Support\Branding;

use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use App\Support\Settings\BusinessSettings;

/**
 * A business's own words for chosen terms (docs/branding.md §3.17), e.g.
 * "Customers" → "Clients", "Work orders" → "Job cards". Only the words in
 * terms() can change: page and menu names, never printed legal wording.
 */
final class Terminology
{
    /** Core words that may be renamed. */
    private const CORE = ['body.dashboard', 'body.customers', 'body.users', 'body.roles'];

    /** Menu words that stay as they are (settings pages). */
    private const KEEP = ['settings'];

    public const MAX_LENGTH = 40;

    /**
     * Every renameable term, as lang key => current wording.
     *
     * @return array<string, string>
     */
    public static function terms(): array
    {
        $keys = self::CORE;
        $modules = app(ModuleManager::class);
        foreach ($modules->cachedHook('modifyAdminMenu') as $module => $items) {
            // Platform tools that are never sold (billing and the like) keep their words.
            if ($modules->billableLocked($module)) {
                continue;
            }
            foreach (is_array($items) ? $items : [] as $item) {
                $label = is_array($item) ? (string) ($item['label'] ?? '') : '';
                if (str_contains($label, '::') && ! collect(self::KEEP)->contains(fn ($keep) => str_contains($label, $keep))) {
                    $keys[] = $label;
                }
            }
        }

        return collect(array_unique($keys))->mapWithKeys(fn ($key) => [$key => (string) __($key)])->all();
    }

    /** @return array<string, string> lang key => the business's word */
    public static function for(Business $business): array
    {
        $saved = (array) app(BusinessSettings::class)->get('branding.terminology', [], $business);

        return array_filter($saved, fn ($word, $key) => is_string($key) && is_string($word) && $word !== '', ARRAY_FILTER_USE_BOTH);
    }

    /** Put the business's words into the translator for this request. */
    public static function apply(Business $business, string $locale): void
    {
        $translator = app('translator');

        foreach (self::for($business) as $key => $word) {
            [$namespace, $item] = str_contains($key, '::') ? explode('::', $key, 2) : ['*', $key];
            [$group, $line] = array_pad(explode('.', $item, 2), 2, null);
            if (! $line) {
                continue;
            }
            // Load the whole group first, or addLines() would stand in for it.
            $translator->load($namespace, $group, $locale);
            $translator->addLines([$group.'.'.$line => $word], $locale, $namespace);
        }
    }
}
