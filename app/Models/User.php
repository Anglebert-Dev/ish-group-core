<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUuidAudit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuidAudit, Notifiable;

    /**
     * Spatie resolves the guard for this model from this property rather
     * than guessing between the "web" and "staff" guards (both of which
     * share the same "users" provider).
     */
    protected $guard_name = 'staff';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned;
    }
}
