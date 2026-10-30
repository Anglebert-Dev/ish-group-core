<?php

namespace Modules\Savings\Http\Controllers\Concerns;

use Closure;
use Illuminate\Http\RedirectResponse;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Exceptions\LikelyDuplicate;
use Modules\Savings\Support\Money;

trait UsesLedger
{
    /**
     * Run a financial action; ledger refusals come back as a message, duplicates with their matches.
     */
    protected function attempt(Closure $action, ?string $success = null, ?Closure $redirect = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (LikelyDuplicate $e) {
            return back()->withInput()->with('error', $e->getMessage())->with('savings_duplicates', $e->matches->map(fn ($t) => [
                'uuid' => $t->uuid, 'date' => $t->effective_date->format('d/m/Y'), 'description' => $t->description, 'amount' => Money::format($t->amount, $t->currency),
            ])->all());
        } catch (LedgerException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return ($redirect ? $redirect($result) : back())->with('success', $success ?? __('savings::savings.posted'));
    }

    /**
     * Parse a money field exactly (no floats).
     */
    protected function money(?string $value, string $currency, bool $allowZero = false): int
    {
        $minor = Money::parse($value ?? '', $currency);

        if (! $allowZero && $minor === 0) {
            throw new \InvalidArgumentException(__('savings::savings.amount_invalid'));
        }

        return $minor;
    }
}
