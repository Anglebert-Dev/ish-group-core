<?php

namespace App\Enums;

enum ActivityLogAction: string
{
    // Record changes (model events)
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    // Account & security
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case MfaEnabled = 'mfa_enabled';
    case MfaDisabled = 'mfa_disabled';
    case ImpersonationStarted = 'impersonation_started';
    case ImpersonationEnded = 'impersonation_ended';

    // Access
    case PermissionsChanged = 'permissions_changed';
    case RoleChanged = 'role_changed';

    // Platform & data
    case ModuleChanged = 'module_changed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Exported = 'exported';
    case Downloaded = 'downloaded';
    case Backup = 'backup';
    case Sent = 'sent';

    public function label(): string
    {
        return __('body.audit_action_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Created, self::Login, self::Approved, self::MfaEnabled => 'success',
            self::Deleted, self::LoginFailed, self::Rejected, self::MfaDisabled => 'danger',
            self::ImpersonationStarted, self::ImpersonationEnded, self::PasswordChanged, self::PasswordReset,
            self::PermissionsChanged, self::RoleChanged => 'warning',
            self::Logout, self::Exported, self::Downloaded => 'muted',
            default => 'brand',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $a) => [$a->value => $a->label()])->all();
    }
}
