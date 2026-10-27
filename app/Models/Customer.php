<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Models\Concerns\HasUuidAudit;
use App\Models\CorporateCustomer\CorporateContact;
use App\Models\CorporateCustomer\CorporateCustomer;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasUuidAudit, Notifiable;

    protected $guard = 'customer';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'national_id',
        'address',
        'driver_license_number',
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

    public function corporateContact(): HasOne
    {
        return $this->hasOne(CorporateContact::class);
    }

    public function isCorporate(): bool
    {
        return $this->corporateContact !== null;
    }

    public function corporateCustomer(): ?CorporateCustomer
    {
        return $this->corporateContact?->corporateCustomer;
    }
}
