<?php

namespace App\Support\Access;

use App\Models\System\System;
use App\Models\User;
use App\Services\Module\ModuleManager;
use App\Services\SuperAdmin\ImpersonationService;

/**
 * Access the operator of the platform keeps for itself.
 *
 * - Platform permissions: permission modules a module marks as platform-only
 *   (DataController::user_permissions() → 'platform_modules'). Only superadmins
 *   and staff of the primary (operator) business can hold them; a customer
 *   business's Admin does not get them automatically.
 * - Staff and roles: with "Businesses manage their own staff and roles" off,
 *   customer businesses can't touch users.user / users.role. A superadmin
 *   signed in as one of their users still can.
 */
class PlatformAccess
{
    public const STAFF_SETTING = 'business_manages_staff';

    private const STAFF_MODULE = 'users';

    private const STAFF_CONTROLLERS = ['user', 'role'];

    /** @var list<string>|null */
    private ?array $platformModules = null;

    private ?int $primaryBusinessId = null;

    private ?bool $staffSetting = null;

    public function __construct(protected ModuleManager $modules)
    {
    }

    /**
     * Decision for Gate::before: true/false, or null to let the usual rules decide.
     */
    public function decide(User $user, string $ability): ?bool
    {
        [$module, $controller] = $this->parse($ability);

        if ($module === null) {
            return null;
        }

        if (in_array($module, $this->platformModules(), true)) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Operator staff: their Admin holds everything, others need the permission.
            return $this->isOperator($user) ? ($user->isBusinessAdmin() ? true : null) : false;
        }

        if ($module === self::STAFF_MODULE && in_array($controller, self::STAFF_CONTROLLERS, true)
            && ! $this->businessesManageStaff() && ! $this->isOperator($user)
            && ! $user->isSuperAdmin() && ! $this->impersonatedBySuperAdmin()) {
            return false;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function platformModules(): array
    {
        if ($this->platformModules !== null) {
            return $this->platformModules;
        }

        $modules = [];

        foreach ($this->modules->cachedHook('user_permissions') as $result) {
            if (is_array($result)) {
                $modules = array_merge($modules, $result['platform_modules'] ?? []);
            }
        }

        return $this->platformModules = array_values(array_unique($modules));
    }

    public function isOperator(?User $user): bool
    {
        $this->primaryBusinessId ??= (int) System::getProperty('primary_business_id');

        return $user !== null && $this->primaryBusinessId > 0 && (int) $user->business_id === $this->primaryBusinessId;
    }

    public function businessesManageStaff(): bool
    {
        return $this->staffSetting ??= System::getProperty(self::STAFF_SETTING, '1') !== '0';
    }

    private function impersonatedBySuperAdmin(): bool
    {
        return app()->bound('request') && request()->hasSession()
            && request()->session()->has(ImpersonationService::SESSION_KEY);
    }

    /**
     * "list injongepay_platform.wallet" → ['injongepay_platform', 'wallet'].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parse(string $ability): array
    {
        if (! preg_match('/^[a-z_]+ ([a-z0-9_]+)\.([a-z0-9_]+)$/', $ability, $m)) {
            return [null, null];
        }

        return [$m[1], $m[2]];
    }
}
