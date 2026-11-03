<?php

namespace Modules\InjongePay\Services;

use App\Models\Business\Business;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\SettlementAccount;

/**
 * Bank accounts and wallets a business cashes out to. The business adds its
 * own; it can be used as soon as GTBank confirms the account under the same
 * holder name.
 */
class SettlementAccounts
{
    public function __construct(protected GtbankClient $bank)
    {
    }

    public function add(Business $business, array $data): SettlementAccount
    {
        $account = new SettlementAccount([
            'kind' => $data['kind'], 'account' => GtbankClient::beneficiary($data['account']), 'holder_name' => trim($data['holder_name']),
            'bank_name' => $data['bank_name'] ?? null, 'bank_code' => $data['bank_code'] ?? null, 'added_by' => auth()->id(),
        ]);
        $account->forceFill(['business_id' => $business->id, 'status' => SettlementAccount::PENDING])->save();

        $this->verify($account);

        return $account->fresh();
    }

    /** Ask GTBank who holds the account; it becomes "verified" only if the names agree. */
    public function verify(SettlementAccount $account): void
    {
        if (! GtbankClient::isConfigured()) {
            $account->forceFill(['status' => SettlementAccount::APPROVED, 'verified_names' => $account->holder_name, 'verified_at' => now(),
                'approved_by' => auth()->id(), 'approved_at' => now(), 'usable_from' => now()])->save();

            return;
        }

        $result = $this->bank->disbursementLookup($account->account, $account);

        if (! $result->ok()) {
            $account->forceFill(['status' => SettlementAccount::PENDING, 'verification_call_id' => $result->callId])->save();

            throw new InjongePayException(__('injongepay::injongepay.err_account_lookup', ['reason' => $result->message()]));
        }

        $found = BankPayloads::account($result->data);
        $matches = self::namesMatch((string) $found['names'], $account->holder_name);

        $account->forceFill([
            'verified_names' => $found['names'], 'verified_bank_code' => $found['bank_code'], 'verified_at' => now(),
            'verification_call_id' => $result->callId, 'bank_name' => $account->bank_name ?: $found['bank_name'],
        ])->save();

        if ($matches && $account->status !== SettlementAccount::APPROVED) {
            $account->forceFill(['status' => SettlementAccount::APPROVED, 'approved_by' => auth()->id(), 'approved_at' => now(), 'usable_from' => now()])->save();
        }

        audit('injongepay_account_verified', __('injongepay::injongepay.audit_account_verified', ['account' => $account->label(), 'names' => $found['names'] ?? '—']),
            $account, ['matches' => $matches], $account->business_id);

        if (! $matches) {
            throw new InjongePayException(__('injongepay::injongepay.err_account_names', ['bank' => $found['names'] ?? '—', 'ours' => $account->holder_name]));
        }
    }

    public function disable(SettlementAccount $account, string $reason): void
    {
        $account->forceFill(['status' => SettlementAccount::DISABLED])->save();
        audit('injongepay_account_disabled', __('injongepay::injongepay.audit_account_disabled', ['account' => $account->label()]), $account, ['reason' => $reason], $account->business_id);
    }

    /** "MUSABYIMANA Rose Mary" = "Rose Mary MUSABYIMANA"; every word of the shorter name in the longer. */
    public static function namesMatch(string $a, string $b): bool
    {
        $words = fn (string $s) => array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtoupper(trim($s))) ?: [], fn ($w) => mb_strlen($w) > 1));
        [$x, $y] = [$words($a), $words($b)];

        if ($x === [] || $y === []) {
            return false;
        }

        [$short, $long] = count($x) <= count($y) ? [$x, $y] : [$y, $x];

        return array_diff($short, $long) === [];
    }
}
