<?php

namespace Modules\InjongePay\Services;

use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Exceptions\StepUpRequired;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Security\Authorization;
use Modules\InjongePay\Services\Security\Devices;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\Security\StepUp;
use Modules\InjongePay\Support\Money;

/**
 * Every money movement out of a wallet: asked for (PIN, step-up when risky),
 * frozen as asked, optionally approved by a second person, then run once.
 */
class Approvals
{
    public const EXPIRE_HOURS = 24;

    public function __construct(
        protected Authorization $authorization,
        protected Pins $pins,
        protected StepUp $stepUp,
        protected Devices $devices,
    ) {
    }

    /**
     * @throws StepUpRequired when a step-up code is needed and none (or a new one) was given
     * @throws InjongePayException
     */
    public function request(Request $request, User $user, Wallet $wallet, string $operation, int $amount, array $payload, ?SettlementAccount $destination, ?string $pin, ?string $code): Approval
    {
        $this->pins->check($user, $pin);
        $checks = $this->authorization->assess($request, $user, $wallet, $operation, $amount, $destination);
        $purpose = $operation.':'.$wallet->id.':'.$amount.':'.($destination?->id ?? ($payload['to_wallet_id'] ?? ''));

        if ($checks['step_up'] !== []) {
            if (blank($code)) {
                $this->stepUp->send($user, $purpose);

                throw new StepUpRequired($checks['step_up'], $this->stepUp->method($user));
            }

            $this->stepUp->check($user, $purpose, $code);

            if (in_array('new_device', $checks['step_up'], true)) {
                $this->devices->trust($request, $user);
            }
        }

        $approval = Approval::query()->create([
            'business_id' => $wallet->business_id, 'wallet_id' => $wallet->id, 'operation' => $operation, 'payload' => $payload,
            'amount' => $amount, 'requested_by' => $user->id,
            'status' => $checks['second_approval'] ? Approval::AWAITING : Approval::APPROVED,
            'checks' => $checks + ['method' => $checks['step_up'] ? $this->stepUp->method($user) : 'pin'],
            'expires_at' => now()->addHours(self::EXPIRE_HOURS),
        ]);

        audit('injongepay_'.$operation.'_requested', __('injongepay::injongepay.audit_requested', ['operation' => __('injongepay::injongepay.operation_'.$operation), 'amount' => Money::format($amount), 'wallet' => $wallet->label()]),
            $approval, ['checks' => $approval->checks], $wallet->business_id);

        if ($checks['second_approval']) {
            app(NotificationService::class)->notifyByPermission('approve injongepay.'.$operation, __('injongepay::injongepay.notify_approval_title'),
                __('injongepay::injongepay.notify_approval_body', ['name' => $user->name, 'operation' => __('injongepay::injongepay.operation_'.$operation), 'amount' => Money::format($amount)]),
                'warning', route('injongepay.approvals.index', [], false), $approval, $wallet->business_id);
        }

        return $approval;
    }

    /** A second person, with their own PIN. Never the one who asked. */
    public function approve(Approval $approval, User $approver, ?string $pin): Approval
    {
        $this->pins->check($approver, $pin);

        return DB::transaction(function () use ($approval, $approver) {
            $locked = Approval::query()->withoutGlobalScopes()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->requested_by === $approver->id) {
                throw new InjongePayException(__('injongepay::injongepay.err_self_approval'));
            }

            if ($locked->status !== Approval::AWAITING) {
                throw new InjongePayException(__('injongepay::injongepay.err_already_resolved'));
            }

            if ($locked->expires_at?->isPast()) {
                $locked->forceFill(['status' => Approval::EXPIRED])->save();

                throw new InjongePayException(__('injongepay::injongepay.err_approval_expired'));
            }

            $locked->forceFill(['status' => Approval::APPROVED, 'approved_by' => $approver->id, 'approved_at' => now()])->save();
            audit('injongepay_approval_approved', __('injongepay::injongepay.audit_approved', ['amount' => Money::format($locked->amount)]), $locked, [], $locked->business_id);

            return $locked;
        });
    }

    public function reject(Approval $approval, User $user, string $reason): void
    {
        DB::transaction(function () use ($approval, $user, $reason) {
            $locked = Approval::query()->withoutGlobalScopes()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Approval::AWAITING) {
                throw new InjongePayException(__('injongepay::injongepay.err_already_resolved'));
            }

            $locked->forceFill(['status' => Approval::REJECTED, 'rejected_by' => $user->id, 'rejection_reason' => $reason])->save();
            audit('injongepay_approval_rejected', __('injongepay::injongepay.audit_rejected', ['amount' => Money::format($locked->amount)]), $locked, ['reason' => $reason], $locked->business_id);
        });
    }

    /**
     * Marks an approved request as taken by its executor, exactly once.
     *
     * @return Approval|null null when it was already run (a double click, a retry)
     */
    public function claim(Approval $approval): ?Approval
    {
        return DB::transaction(function () use ($approval) {
            $locked = Approval::query()->withoutGlobalScopes()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Approval::APPROVED || $locked->executed_at !== null) {
                return null;
            }

            $locked->forceFill(['executed_at' => now(), 'status' => Approval::EXECUTED])->save();

            return $locked;
        });
    }

    /** @return int how many expired */
    public function expireOverdue(): int
    {
        $count = 0;

        foreach (Approval::query()->withoutGlobalScopes()->where('status', Approval::AWAITING)->where('expires_at', '<', now())->get() as $approval) {
            $expired = DB::transaction(function () use ($approval) {
                $locked = Approval::query()->withoutGlobalScopes()->whereKey($approval->id)->lockForUpdate()->first();

                if ($locked->status !== Approval::AWAITING) {
                    return false;
                }

                $locked->forceFill(['status' => Approval::EXPIRED])->save();

                return true;
            });

            if (! $expired) {
                continue;
            }

            $count++;
            audit('injongepay_approval_expired', __('injongepay::injongepay.audit_expired', ['amount' => Money::format($approval->amount)]), $approval, [], $approval->business_id);

            try {
                app(NotificationService::class)->notify($approval->requested_by, __('injongepay::injongepay.notify_approval_expired'),
                    __('injongepay::injongepay.notify_approval_expired_body', ['operation' => __('injongepay::injongepay.operation_'.$approval->operation), 'amount' => Money::format($approval->amount)]),
                    'warning', route('injongepay.approvals.index', [], false), $approval);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    public function fail(Approval $approval, string $reason): void
    {
        $approval->forceFill(['status' => Approval::FAILED, 'failure_reason' => $reason])->save();
        audit('injongepay_approval_failed', __('injongepay::injongepay.audit_failed', ['reason' => $reason]), $approval, [], $approval->business_id);
    }
}
