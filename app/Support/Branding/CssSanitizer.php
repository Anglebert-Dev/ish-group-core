<?php

namespace App\Support\Branding;

/**
 * A business's custom CSS (docs/branding.md §3.13), rebuilt rather than
 * filtered: comments dropped, anything that could load, run or escape
 * refused, and every selector put inside the page content (.app-content),
 * so the menu, header, sign-in pages and anything security-related can't
 * be restyled. No HTML, no scripts, ever.
 */
final class CssSanitizer
{
    public const MAX_BYTES = 20000;

    /** Refused anywhere in the CSS (after comments are removed). */
    private const FORBIDDEN = [
        '@import' => '/@import/i',
        '@charset' => '/@charset/i',
        '@namespace' => '/@namespace/i',
        '@font-face' => '/@font-face/i',
        'url()' => '/url\s*\(/i',
        'expression()' => '/expression\s*\(/i',
        'javascript:' => '/javascript\s*:/i',
        'behavior' => '/behavio(u)?r\s*:/i',
        '-moz-binding' => '/-moz-binding/i',
        'HTML' => '/[<>]/',
        'escapes (\\)' => '/\\\\/',
    ];

    private const SCOPE = '.app-content';

    /**
     * @return array{css: string, errors: list<string>}
     */
    public static function clean(?string $css): array
    {
        $css = trim(preg_replace('#/\*.*?\*/#s', '', (string) $css) ?? '');
        if ($css === '') {
            return ['css' => '', 'errors' => []];
        }
        if (strlen($css) > self::MAX_BYTES) {
            return ['css' => '', 'errors' => [__('body.css_too_long', ['max' => self::MAX_BYTES / 1000])]];
        }

        $errors = [];
        foreach (self::FORBIDDEN as $label => $pattern) {
            if (preg_match($pattern, $css)) {
                $errors[] = __('body.css_forbidden', ['what' => $label]);
            }
        }
        if (substr_count($css, '{') !== substr_count($css, '}')) {
            $errors[] = __('body.css_unbalanced');
        }

        return $errors === [] ? ['css' => self::rules($css), 'errors' => []] : ['css' => '', 'errors' => $errors];
    }

    /** Blocks one after another; @media/@supports keep their scoped inner rules. */
    private static function rules(string $css): string
    {
        $out = '';
        $offset = 0;
        $length = strlen($css);

        while ($offset < $length) {
            $open = strpos($css, '{', $offset);
            if ($open === false) {
                break;
            }
            $close = self::matchingBrace($css, $open);
            if ($close === null) {
                break;
            }

            $selector = trim(substr($css, $offset, $open - $offset));
            $body = substr($css, $open + 1, $close - $open - 1);
            $offset = $close + 1;

            if (preg_match('/^@(media|supports)\b/i', $selector)) {
                $out .= $selector.'{'.self::rules($body).'}';
            } elseif (preg_match('/^@keyframes\s+[\w-]+$/i', $selector)) {
                $out .= $selector.'{'.self::keyframes($body).'}';
            } elseif ($selector !== '' && $selector[0] !== '@') {
                $declarations = self::declarations($body);
                if ($declarations !== '') {
                    $out .= self::scope($selector).'{'.$declarations.'}';
                }
            }
        }

        return $out;
    }

    private static function keyframes(string $body): string
    {
        return preg_replace_callback('/([\w%,\s.]+)\{([^{}]*)\}/', fn ($m) => trim($m[1]).'{'.self::declarations($m[2]).'}', $body) ?? '';
    }

    private static function scope(string $selectors): string
    {
        return collect(explode(',', $selectors))
            ->map(fn ($selector) => trim($selector))
            ->filter()
            // Already scoped (the stored CSS is cleaned again whenever it is read).
            // ".app-content", ".app-content h1", ".app-content:hover"… but never a sibling (~, +) that reaches outside.
            ->map(fn ($selector) => preg_match('/^\.app-content(?![\w-])(?!\s*[~+])/', $selector) ? $selector : (preg_match('/^(html|body|:root)\b/i', $selector)
                ? self::SCOPE.preg_replace('/^(html|body|:root)/i', '', $selector)
                : self::SCOPE.' '.$selector))
            ->implode(',');
    }

    private static function declarations(string $body): string
    {
        return collect(explode(';', $body))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => preg_match('/^-?[a-z][a-z0-9-]*\s*:\s*[^{}]+$/i', $line))
            // Nothing may sit fixed over the whole page.
            ->reject(fn ($line) => preg_match('/^position\s*:\s*fixed/i', $line))
            ->implode(';');
    }

    private static function matchingBrace(string $css, int $open): ?int
    {
        $depth = 0;
        for ($i = $open, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }
}
