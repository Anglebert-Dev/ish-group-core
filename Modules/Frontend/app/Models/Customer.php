<?php

namespace Modules\Frontend\Models;

use App\Models\Business\Business;
use App\Models\Concerns\HasUuidAudit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Frontend\Enums\CustomerStatus;

/**
 * Public-site account ("customer" guard). Belongs to the business the
 * public site represents (system "primary_business_id").
 */
class Customer extends Authenticatable
{
    use HasUuidAudit, Notifiable;

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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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
        return url(route('customer.password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));
    }
}
