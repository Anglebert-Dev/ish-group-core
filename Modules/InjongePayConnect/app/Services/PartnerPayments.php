<?php

namespace Modules\InjongePayConnect\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;
use Modules\InjongePayConnect\Models\Confirmation;
use Modules\InjongePayConnect\Models\Connection;

/**
 * What partners may read and do with a business's payments. Never touches a
 * balance: a confirmation only links a payment to an invoice.
 *
 * Fixed here without changing the contract:
 *  - a cashed-out payment is still AVAILABLE or CONFIRMED in the filters;
 *  - updated_since also returns payments whose confirmation changed;
 *  - a payment GTBank reversed can't be confirmed;
 */
class PartnerPayments
{
    public function wallets(Connection $connection): Collection
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')->where('business_id', $connection->business_id)->orderBy('id')->get();
    }

    public function wallet(Connection $connection, string $uuid): ?Wallet
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')->where('business_id', $connection->business_id)->whereUuid($uuid)->first();
    }

    public function payment(Connection $connection, string $uuid): ?Payment
    {
        return Payment::query()->withoutGlobalScopes()->where('business_id', $connection->business_id)->whereUuid($uuid)->first();
    }

    public function list(Wallet $wallet, array $filters): LengthAwarePaginator
    {
        $active = fn ($q) => $q->select(DB::raw(1))->from('ijpc_confirmations')->whereColumn('ijpc_confirmations.payment_id', 'ijp_payments.id')->where('ijpc_confirmations.status', Confirmation::CONFIRMED);
        $query = Payment::query()->withoutGlobalScopes()->where('wallet_id', $wallet->id);

        match ($filters['status'] ?? null) {
            'REVERSED' => $query->where('payment_status', Payment::STATUS_REVERSED),
            'AVAILABLE' => $query->where('payment_status', '!=', Payment::STATUS_REVERSED)->whereNotExists($active),
            'CONFIRMED' => $query->where('payment_status', '!=', Payment::STATUS_REVERSED)->whereExists($active),
            default => null,
        };

        if (! empty($filters['amount']) && is_numeric($filters['amount'])) {
            $query->where('gross_amount', Money::toCents(number_format((float) $filters['amount'], 2, '.', '')));
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', self::moment($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', self::moment($filters['date_to']));
        }

        if (! empty($filters['updated_since'])) {
            $since = self::moment($filters['updated_since']);
            $query->where(fn ($q) => $q->where('updated_at', '>', $since)->orWhereExists(fn ($c) => $c->select(DB::raw(1))->from('ijpc_confirmations')
                ->whereColumn('ijpc_confirmations.payment_id', 'ijp_payments.id')->where('ijpc_confirmations.updated_at', '>', $since)));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('buyer_phone', 'like', $term)->orWhere('buyer_name', 'like', $term)->orWhere('bank_reference', 'like', $term));
        }

        // 0 falls back to the framework's own default, as it always has for partners.
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 0), 200) ?: null;

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @param iterable<Payment> $payments */
    public function activeFor(iterable $payments): Collection
    {
        $ids = collect($payments)->pluck('id');

        return $ids->isEmpty() ? collect() : Confirmation::query()->withoutGlobalScopes()->whereIn('payment_id', $ids)->where('status', Confirmation::CONFIRMED)
            ->orderByDesc('id')->get()->unique('payment_id')->keyBy('payment_id');
    }

    public function active(Payment $payment): ?Confirmation
    {
        return $this->activeFor([$payment])->get($payment->id);
    }

    /**
     * @throws AlreadyConfirmed
     * @throws InjongePayException a reversed payment
     */
    public function confirm(Connection $connection, Payment $payment, array $data): Confirmation
    {
        $confirmation = DB::transaction(function () use ($connection, $payment, $data) {
            $locked = Payment::query()->withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->payment_status === Payment::STATUS_REVERSED) {
                throw new InjongePayException(__('injongepayconnect::connect.err_reversed'));
            }

            $existing = Confirmation::query()->withoutGlobalScopes()->where('payment_id', $locked->id)->where('status', Confirmation::CONFIRMED)->lockForUpdate()->first();

            if ($existing) {
                if (! empty($data['idempotency_key']) && $existing->confirmation_idempotency_key === $data['idempotency_key'] && $existing->invoice_no === ($data['invoice_no'] ?? null)) {
                    return $existing;
                }

                throw new AlreadyConfirmed($existing->invoice_no);
            }

            return Confirmation::query()->create([
                'payment_id' => $locked->id, 'wallet_id' => $locked->wallet_id, 'business_id' => $locked->business_id, 'connection_id' => $connection->id,
                'status' => Confirmation::CONFIRMED, 'confirmation_idempotency_key' => $data['idempotency_key'] ?? null,
                'invoice_no' => $data['invoice_no'], 'ebm_receipt_no' => $data['ebm_receipt_no'] ?? null, 'sdc_id' => $data['sdc_id'] ?? null,
                'is_partial' => (bool) ($data['partial'] ?? false), 'note' => $data['note'] ?? null,
                'invoice_total' => $data['invoice_total'] ?? null, 'balance_after' => $data['balance_after'] ?? null,
                'confirmed_by' => $data['cashier']['id'] ?? null, 'confirmed_by_name' => $data['cashier']['name'] ?? null,
                'confirmed_at' => isset($data['confirmed_at']) ? Carbon::parse($data['confirmed_at']) : now(),
            ]);
        });

        if ($confirmation->wasRecentlyCreated) {
            audit('injongepay_payment_confirmed', __('injongepayconnect::connect.audit_confirmed', ['invoice' => $confirmation->invoice_no, 'ref' => $payment->bank_reference]),
                $confirmation, ['connection' => $connection->client_id, 'partial' => $confirmation->is_partial], $payment->business_id);
        }

        return $confirmation;
    }

    /** @throws InjongePayException when there is nothing to undo */
    public function unconfirm(Connection $connection, Payment $payment, array $data): Confirmation
    {
        $confirmation = DB::transaction(function () use ($payment, $data) {
            $row = Confirmation::query()->withoutGlobalScopes()->where('payment_id', $payment->id)->where('status', Confirmation::CONFIRMED)->lockForUpdate()->first();

            if (! $row) {
                throw new InjongePayException(__('injongepayconnect::connect.err_not_confirmed'));
            }

            $row->forceFill([
                'status' => Confirmation::UNCONFIRMED, 'unconfirmed_by' => $data['manager']['id'] ?? null, 'unconfirmed_by_name' => $data['manager']['name'] ?? null,
                'unconfirmed_at' => now(), 'unconfirm_reason' => $data['reason'],
            ])->save();

            return $row;
        });

        audit('injongepay_payment_unconfirmed', __('injongepayconnect::connect.audit_unconfirmed', ['invoice' => $confirmation->invoice_no, 'ref' => $payment->bank_reference]),
            $confirmation, ['connection' => $connection->client_id, 'reason' => $data['reason']], $payment->business_id);

        return $confirmation;
    }

    public function byInvoice(Connection $connection, string $invoiceNo): Collection
    {
        return Confirmation::query()->withoutGlobalScopes()->with('payment')->where('business_id', $connection->business_id)->where('invoice_no', $invoiceNo)->orderBy('confirmed_at')->orderBy('id')->get();
    }

    /** @return array{date: string, count: int, gross_total: float, fee_total: float, net_total: float} */
    public function totals(Wallet $wallet, string $date): array
    {
        $day = Carbon::parse($date, (string) config('injongepayconnect.timezone'));
        $row = Payment::query()->withoutGlobalScopes()->where('wallet_id', $wallet->id)
            ->whereBetween('created_at', [$day->copy()->startOfDay()->setTimezone(config('app.timezone')), $day->copy()->endOfDay()->setTimezone(config('app.timezone'))])
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(gross_amount), 0) AS g, COALESCE(SUM(fee_amount), 0) AS f, COALESCE(SUM(net_amount), 0) AS n')->first();

        return ['date' => $date, 'count' => (int) $row->c, 'gross_total' => Money::toFloat((int) $row->g), 'fee_total' => Money::toFloat((int) $row->f), 'net_total' => Money::toFloat((int) $row->n)];
    }

    /** "2026-10-09" → midnight that day; a date-time is taken as given. */
    private static function moment(string $value): Carbon
    {
        $at = Carbon::parse($value, (string) config('injongepayconnect.timezone'));

        return $at->setTimezone((string) config('app.timezone'));
    }
}
