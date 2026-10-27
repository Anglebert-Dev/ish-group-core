<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\Concerns\HasUuidAudit;
use App\Models\Role\Role;
use App\Support\Tenancy\CurrentBusiness;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'is_superadmin' => 'boolean',
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

    /**
     * {user} route parameters only resolve to users of the signed-in staff
     * member's business (User is not globally scoped — see BelongsToBusiness).
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);
        $businessId = app(CurrentBusiness::class)->id();

        return $businessId !== null ? $query->where('business_id', $businessId) : $query;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Platform-level power (manage modules, businesses). Granted only by the
     * is_superadmin flag, never by a role.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_superadmin;
    }

    /**
     * Tenant god-mode: holds this business's locked Admin#<id> role.
     */
    public function isBusinessAdmin(): bool
    {
        return $this->business_id !== null
            && $this->hasRole(Role::scopedName(Role::ADMIN, (int) $this->business_id));
    }

    public function roleName(): ?string
    {
        $role = $this->roles->first();

        return $role ? Role::displayName($role->name) : null;
    }
}
