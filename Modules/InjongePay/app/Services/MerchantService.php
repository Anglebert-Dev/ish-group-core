<?php

namespace Modules\InjongePay\Services;

use App\Models\Business\Business;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Jobs\RegisterMerchant;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\BusinessAlert;
use Modules\InjongePay\Support\PlatformAlert;

/**
 * A wallet's merchant code at GTBank (v2.5 §6):
 *
 *   NOT_REGISTERED → REGISTERING → PENDING → ACTIVE / INACTIVE / CLOSED
 *                         └→ FAILED (GTBank refused; fix KYC and retry)
 *
 * A network problem never changes the state: it is recorded in bank_error and
 * the action can be retried. bank_status only ever shows what GTBank said;
 * is_enabled is the platform's own switch and the sync never touches it.
 */
class MerchantService
{
    public const CONFIRM_MISSING_AFTER_MINUTES = 10;

    private const BANK_STATUSES = [Wallet::BANK_ACTIVE, Wallet::BANK_PENDING, Wallet::BANK_INACTIVE, Wallet::BANK_CLOSED];

    public function __construct(protected GtbankClient $bank, protected MerchantCodes $codes)
    {
    }

    /**
     * New wallet with the next merchant code; registration at GTBank is queued
     * (after $registerAfter seconds, so a batch reaches GTBank a few at a time).
     */
    public function create(Business $business, array $data, int $registerAfter = 0): Wallet
    {
        $wallet = DB::transaction(function () use ($business, $data) {
            $wallet = new Wallet(array_intersect_key($data, array_flip(['name', 'kyc_business_owner', 'kyc_tin', 'kyc_contact_phone', 'kyc_contact_email'])) + [
                'is_enabled' => true,
                'bank_status' => Wallet::BANK_NOT_REGISTERED,
            ]);
            $wallet->forceFill(['business_id' => $business->id, 'merchant_code' => $this->codes->next()])->save();

            return $wallet;
        });

        $this->audit('injongepay_wallet_created', $wallet, ['merchant_code' => $wallet->merchant_code]);
        RegisterMerchant::dispatch($wallet->id)->delay(now()->addSeconds($registerAfter))->afterCommit();

        return $wallet;
    }

    public function register(Wallet $wallet): BankResult
    {
        if ($wallet->isInternal()) {
            throw new InjongePayException(__('injongepay::injongepay.err_internal_wallet_bank'));
        }

        if (! GtbankClient::isConfigured()) {
            // Test mode: nothing goes to a bank, the wallet takes payments at once.
            $this->apply($wallet, ['bank_status' => Wallet::BANK_ACTIVE, 'bank_error' => null, 'bank_synced_at' => now()]);

            return new BankResult(BankResult::OK);
        }

        $previous = $wallet->bank_status;
        $this->apply($wallet, ['bank_status' => Wallet::BANK_REGISTERING]);

        // Registered before (e.g. an earlier attempt timed out after GTBank accepted it)?
        $lookup = $this->bank->lookupMerchant($wallet->merchant_code, $wallet);

        if ($lookup->ok()) {
            return $this->applyLookup($wallet, $lookup, $previous);
        }

        if ($lookup->unknown() || $lookup->rejected()) {
            $this->failSoft($wallet, $previous, $lookup);

            return $lookup;
        }

        $details = $this->details($wallet);
        if ($missing = $this->missingKyc($details)) {
            $this->apply($wallet, ['bank_status' => Wallet::BANK_FAILED, 'bank_error' => __('injongepay::injongepay.err_kyc_missing', ['field' => $missing]), 'bank_error_at' => now()]);
            $this->audit('injongepay_wallet_registration_failed', $wallet, ['reason' => 'kyc', 'field' => $missing]);

            return new BankResult(BankResult::REJECTED, message: $wallet->bank_error);
        }

        $result = $this->bank->registerMerchant(['merchantId' => $wallet->merchant_code] + $details + ['channel' => (string) config('injongepay.gtbank.participant_name')], $wallet);

        if ($result->ok() || ($result->rejected() && $result->code === '02')) {
            // 02 = already exists: GTBank has it, the next sync reads its status.
            $this->apply($wallet, ['bank_status' => Wallet::BANK_PENDING, 'bank_error' => null, 'bank_synced_at' => now()]);
            $this->audit('injongepay_wallet_registered', $wallet, ['bank_call' => $result->callId]);
        } elseif ($result->rejected()) {
            $this->apply($wallet, ['bank_status' => Wallet::BANK_FAILED, 'bank_error' => $result->message(), 'bank_error_at' => now()]);
            $this->audit('injongepay_wallet_registration_failed', $wallet, ['reason' => $result->message(), 'code' => $result->code, 'bank_call' => $result->callId]);
        } else {
            // Unknown: GTBank may have it. Stay REGISTERING; the sync's lookup settles it.
            $this->apply($wallet, ['bank_error' => $result->message(), 'bank_error_at' => now()]);
        }

        return $result;
    }

    /** Ask GTBank where the merchant code stands ("Sync from GTBank" and the scheduled sync). */
    public function sync(Wallet $wallet): BankResult
    {
        if ($wallet->isInternal() || ! GtbankClient::isConfigured()) {
            return new BankResult(BankResult::OK);
        }

        $result = $this->bank->lookupMerchant($wallet->merchant_code, $wallet);

        if ($result->ok()) {
            return $this->applyLookup($wallet, $result, $wallet->bank_status);
        }

        if ($result->notFound() && $this->isLive($wallet) && ! $this->confirmedMissing($wallet)) {
            // One answer from GTBank can be wrong: payments keep flowing until a later check agrees.
            $this->apply($wallet, ['bank_missing_since' => $wallet->bank_missing_since ?? now(), 'bank_error' => __('injongepay::injongepay.err_bank_missing_once'), 'bank_error_at' => now(), 'bank_synced_at' => now()]);
            $this->audit('injongepay_wallet_bank_status', $wallet, ['status' => $wallet->bank_status, 'reason' => 'not_found_once']);

            return $result;
        }

        if ($result->notFound() && in_array($wallet->bank_status, [Wallet::BANK_PENDING, Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE, Wallet::BANK_REGISTERING], true)) {
            $wasLive = $this->isLive($wallet);
            $this->apply($wallet, ['bank_status' => Wallet::BANK_FAILED, 'bank_error' => __('injongepay::injongepay.err_bank_no_record'), 'bank_error_at' => now(), 'bank_synced_at' => now()]);
            $wallet->forceFill(['bank_missing_since' => null])->save();
            $this->audit('injongepay_wallet_bank_status', $wallet, ['status' => Wallet::BANK_FAILED, 'reason' => 'not_found']);

            if ($wasLive) {
                $this->tellCodeStopped($wallet, 'alert_code_missing');
            }

            return $result;
        }

        if (! $result->notFound()) {
            $this->failSoft($wallet, $wallet->bank_status, $result);
        }

        return $result;
    }

    /**
     * Change the KYC GTBank holds for this merchant code. GTBank must accept it
     * first; it applies it later (PENDING), so the change shows as waiting
     * until a sync sees it.
     */
    public function updateDetails(Wallet $wallet, array $data): BankResult
    {
        $overrides = array_intersect_key($data, array_flip(['name', 'kyc_business_owner', 'kyc_tin', 'kyc_contact_phone', 'kyc_contact_email']));

        if (! in_array($wallet->bank_status, self::BANK_STATUSES, true) || ! GtbankClient::isConfigured()) {
            // Not at GTBank yet: the details go with the next registration.
            $this->apply($wallet, $overrides);

            return new BankResult(BankResult::OK);
        }

        $candidate = (clone $wallet)->forceFill($overrides);
        $payload = ['merchantId' => $wallet->merchant_code] + $this->details($candidate);

        if ($missing = $this->missingKyc($payload)) {
            throw new InjongePayException(__('injongepay::injongepay.err_kyc_missing', ['field' => $missing]));
        }

        $result = $this->bank->updateMerchant($payload, $wallet);

        if (! $result->ok()) {
            throw new InjongePayException(__('injongepay::injongepay.err_bank_update', ['reason' => $result->message()]));
        }

        $this->apply($wallet, $overrides + ['bank_update_pending_at' => now(), 'bank_error' => null]);
        $this->audit('injongepay_wallet_bank_update', $wallet, ['changes' => array_keys($overrides), 'bank_call' => $result->callId]);

        return $result;
    }

    /** Closes the merchant code at GTBank (it can't take payments any more) and switches the wallet off. */
    public function close(Wallet $wallet, string $reason): BankResult
    {
        if ($wallet->balance() !== 0) {
            throw new InjongePayException(__('injongepay::injongepay.err_close_with_balance'));
        }

        $result = in_array($wallet->bank_status, self::BANK_STATUSES, true) && GtbankClient::isConfigured()
            ? $this->bank->closeMerchant($wallet->merchant_code, $wallet)
            : new BankResult(BankResult::OK);

        if (! $result->ok() && ! $result->notFound()) {
            throw new InjongePayException(__('injongepay::injongepay.err_bank_close', ['reason' => $result->message()]));
        }

        $this->apply($wallet, ['is_enabled' => false, 'bank_update_pending_at' => GtbankClient::isConfigured() ? now() : null]
            + (GtbankClient::isConfigured() ? [] : ['bank_status' => Wallet::BANK_CLOSED]));
        $this->audit('injongepay_wallet_closed', $wallet, ['reason' => $reason, 'bank_call' => $result->callId]);

        return $result;
    }

    public function setEnabled(Wallet $wallet, bool $enabled, string $reason): void
    {
        $this->apply($wallet, ['is_enabled' => $enabled]);
        $this->audit($enabled ? 'injongepay_wallet_enabled' : 'injongepay_wallet_disabled', $wallet, ['reason' => $reason]);
    }

    /**
     * What we send GTBank: wallet values first, then the business's KYC.
     *
     * @return array{businessName: ?string, businessOwner: ?string, taxIdentificationNumber: ?string, contactPhone: ?string, contactEmail: ?string}
     */
    public function details(Wallet $wallet): array
    {
        $business = Business::query()->find($wallet->business_id);
        $profile = $business ? BusinessProfile::for($business) : null;

        return [
            'businessName' => $business?->name,
            'businessOwner' => $wallet->kyc_business_owner ?: $profile?->contact_person,
            'taxIdentificationNumber' => $wallet->kyc_tin ?: $profile?->tin,
            'contactPhone' => $wallet->kyc_contact_phone ?: $profile?->contact_phone,
            'contactEmail' => $wallet->kyc_contact_email ?: $profile?->contact_email,
        ];
    }

    private function applyLookup(Wallet $wallet, BankResult $result, ?string $previous): BankResult
    {
        $merchant = BankPayloads::merchant($result->data);

        // Never apply an answer about another merchant code.
        if ($merchant['merchant_id'] !== null && $merchant['merchant_id'] !== $wallet->merchant_code) {
            $this->failSoft($wallet, $previous, new BankResult(BankResult::REJECTED, message: __('injongepay::injongepay.err_bank_wrong_merchant', ['asked' => $wallet->merchant_code, 'got' => $merchant['merchant_id']])));

            return new BankResult(BankResult::REJECTED, $result->data, message: $wallet->bank_error);
        }

        $status = in_array($merchant['status'], self::BANK_STATUSES, true) ? $merchant['status'] : ($previous === Wallet::BANK_REGISTERING ? Wallet::BANK_PENDING : $previous);
        $nameMatches = $merchant['name'] === null || strcasecmp($merchant['name'], (string) Business::query()->whereKey($wallet->business_id)->value('name')) === 0;

        $changes = [
            'bank_status' => $status,
            'bank_registered_name' => $merchant['name'] ?? $wallet->bank_registered_name,
            'bank_registered_owner' => $merchant['owner'] ?? $wallet->bank_registered_owner,
            'bank_synced_at' => now(),
            'bank_error' => $status === Wallet::BANK_INACTIVE ? __('injongepay::injongepay.err_bank_inactive') : null,
            'bank_missing_since' => null,
        ];

        if ($wallet->bank_update_pending_at && $nameMatches) {
            $changes['bank_update_pending_at'] = null;
        }

        $this->apply($wallet, $changes);

        if ($status !== $previous) {
            $this->audit('injongepay_wallet_bank_status', $wallet, ['from' => $previous, 'to' => $status, 'bank_call' => $result->callId]);

            if ($previous === Wallet::BANK_ACTIVE && in_array($status, [Wallet::BANK_INACTIVE, Wallet::BANK_CLOSED], true)) {
                $this->tellCodeStopped($wallet, 'alert_code_inactive');
            }
        }

        return $result;
    }

    private function isLive(Wallet $wallet): bool
    {
        return in_array($wallet->bank_status, [Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE], true);
    }

    /** A second "no record", at least CONFIRM_MISSING_AFTER_MINUTES after the first. */
    private function confirmedMissing(Wallet $wallet): bool
    {
        return $wallet->bank_missing_since !== null && $wallet->bank_missing_since->lte(now()->subMinutes(self::CONFIRM_MISSING_AFTER_MINUTES));
    }

    /** Injonge Pay must act; the business must know customers can't pay into this code. */
    private function tellCodeStopped(Wallet $wallet, string $key): void
    {
        $title = __('injongepay::injongepay.'.$key.'_title');
        $business = Business::query()->whereKey($wallet->business_id)->value('name');

        PlatformAlert::send('list injongepay_platform.wallet', $title, __('injongepay::injongepay.'.$key.'_platform', ['code' => $wallet->merchant_code, 'name' => $wallet->name, 'business' => $business]),
            route('injongepay.platform.wallets.show', $wallet->uuid, false));
        BusinessAlert::send((int) $wallet->business_id, $title, __('injongepay::injongepay.'.$key.'_business', ['code' => $wallet->merchant_code, 'name' => $wallet->name]),
            route('injongepay.wallets.show', $wallet->uuid, false));
    }

    /** A call that didn't get an answer: keep the state GTBank last gave us, note the problem. */
    private function failSoft(Wallet $wallet, ?string $previous, BankResult $result): void
    {
        $this->apply($wallet, ['bank_status' => $previous ?? Wallet::BANK_NOT_REGISTERED, 'bank_error' => $result->message(), 'bank_error_at' => now()]);
    }

    private function missingKyc(array $details): ?string
    {
        $labels = ['businessName' => 'body.business_name', 'businessOwner' => 'injongepay::injongepay.kyc_contact_person', 'taxIdentificationNumber' => 'injongepay::injongepay.kyc_tin',
            'contactPhone' => 'injongepay::injongepay.kyc_contact_phone', 'contactEmail' => 'injongepay::injongepay.kyc_contact_email'];

        foreach ($labels as $key => $label) {
            if (blank($details[$key] ?? null)) {
                return __($label);
            }
        }

        if (! preg_match('/^\+250\d{9}$/', (string) $details['contactPhone'])) {
            return __('injongepay::injongepay.kyc_contact_phone');
        }

        return null;
    }

    private function apply(Wallet $wallet, array $changes): void
    {
        $wallet->forceFill($changes)->save();
    }

    private function audit(string $action, Wallet $wallet, array $details = []): void
    {
        audit($action, __('injongepay::audit.'.$action).': '.$wallet->label(), $wallet, $details + ['merchant_code' => $wallet->merchant_code], $wallet->business_id);
    }
}
