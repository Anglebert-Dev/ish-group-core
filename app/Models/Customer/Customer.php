<?php

namespace App\Models\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasUuidAudit;
use Closure;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A business's customer (client). Core data every business has; modules
 * link their orders, invoices, deliveries… to it. Website login is
 * optional: it needs the Frontend module and a password (portal access).
 */
class Customer extends Authenticatable
{
    use BelongsToBusiness, HasUuidAudit, Notifiable;

    protected $guard = 'customer';

    protected $fillable = [
        'type',
        'name',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        'address',
        'delivery_address',
        'payment_terms_days',
        'notes',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Set by the module that provides customer logins (Frontend), so reset
     * links point at the right website.
     *
     * @var (Closure(Customer, string): string)|null
     */
    public static ?Closure $resetUrlResolver = null;

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'status' => CustomerStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'payment_terms_days' => 'integer',
        ];
    }

    /**
     * Name to show: the company for companies, else the person.
     */
    public function displayName(): string
    {
        return $this->type === CustomerType::Company && filled($this->company_name)
            ? $this->company_name
            : (string) $this->name;
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

    public function passwordResetUrl(string $token): string
    {
        return self::$resetUrlResolver
            ? (self::$resetUrlResolver)($this, $token)
            : url('/');
    }
}
