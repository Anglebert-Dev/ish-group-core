<?php

namespace Modules\Frontend\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasUuidAudit;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Frontend\Enums\CustomerStatus;

/**
 * Public-site account ("customer" guard). Belongs to the business whose
 * site they signed up on; scoped to it by BelongsToBusiness.
 */
class Customer extends Authenticatable
{
    use BelongsToBusiness, HasUuidAudit, Notifiable;

    protected $guard = 'customer';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => CustomerStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    public function isBanned(): bool
    {
        return $this->status === CustomerStatus::Banned;
    }

    public function hasPortalPassword(): bool
    {
        return filled($this->getAttributes()['password'] ?? null);
    }

    /**
     * Used by the core ResetPassword URL callback (AppServiceProvider).
     */
    public function passwordResetUrl(string $token): string
    {
        $slug = Site::query()->withoutGlobalScope('business')
            ->where('business_id', $this->business_id)
            ->value('slug');

        return url(route('customer.password.reset', [
            'site' => $slug,
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));
    }
}
