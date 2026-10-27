<?php

namespace App\Listeners;

use App\Enums\ActivityLogAction;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Sign-ins, sign-outs, failed attempts and password resets for every guard
 * (staff and, with the Frontend module, customers).
 */
class AuditAuthEvents
{
    public function __construct(protected ActivityLogService $audit)
    {
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            PasswordReset::class => 'passwordReset',
        ];
    }

    public function login(Login $event): void
    {
        $this->audit->log(
            ActivityLogAction::Login,
            __('body.audit_log_login', ['name' => $event->user->name ?? '', 'portal' => $this->portal($event->guard)]),
            $event->user,
            ['guard' => $event->guard, 'remember' => $event->remember],
            $event->user->business_id ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }

    public function logout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        $this->audit->log(
            ActivityLogAction::Logout,
            __('body.audit_log_logout', ['name' => $event->user->name ?? '', 'portal' => $this->portal($event->guard)]),
            $event->user,
            ['guard' => $event->guard],
            $event->user->business_id ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }

    public function failed(Failed $event): void
    {
        $login = $event->credentials['email'] ?? $event->credentials['login'] ?? '';

        $this->audit->log(
            ActivityLogAction::LoginFailed,
            __('body.audit_log_failed', ['login' => $login, 'portal' => $this->portal($event->guard)]),
            $event->user,
            ['guard' => $event->guard, 'login' => $login],
            $event->user->business_id ?? null,
        );
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->audit->log(
            ActivityLogAction::PasswordReset,
            __('body.audit_log_password_reset', ['name' => $event->user->name ?? '']),
            $event->user,
            [],
            $event->user->business_id ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }

    private function portal(?string $guard): string
    {
        return $guard === 'customer' ? __('body.audit_portal_website') : __('body.audit_portal_staff');
    }
}
