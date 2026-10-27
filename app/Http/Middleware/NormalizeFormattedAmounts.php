<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeFormattedAmounts
{
    /**
     * Strip thousand separators (e.g. 65,000 → 65000) from submitted amount-like strings.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->normalize($request->all()));

        return $next($request);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalize($item);
            }

            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        // 1,234 or 1,234.50 (comma groups of three)
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            return str_replace(',', '', $value);
        }

        return $value;
    }
}
