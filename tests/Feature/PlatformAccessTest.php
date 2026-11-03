<?php

namespace Tests\Feature;

use App\Enums\ActivityLogAction;
use App\Http\Middleware\LogoutIdleStaff;
use App\Models\ActivityLog\ActivityLog;
use App\Models\System\System;
use App\Services\Module\ModuleManager;
use App\Services\SuperAdmin\ImpersonationService;
use App\Support\Access\PlatformAccess;
use Tests\TestCase;

/**
 * Operator-only access (platform permissions, staff & roles switch) and the idle sign-out.
 */
class PlatformAccessTest extends TestCase
{
    private function platformModules(array $modules): void
    {
        $this->app->scoped(PlatformAccess::class, fn ($app) => new class($app->make(ModuleManager::class), $modules) extends PlatformAccess
        {
            public function __construct(ModuleManager $modules, private array $fixed)
            {
                parent::__construct($modules);
            }

            public function platformModules(): array
            {
                return $this->fixed;
            }
        });
    }

    public function test_platform_permissions_are_denied_to_a_customer_business_admin(): void
    {
        $this->platformModules(['demo_platform']);
        [$operator, $operatorOwner] = $this->makeBusiness();
        System::addProperty('primary_business_id', $operator->id);
        [, $customerOwner] = $this->makeBusiness();

        $this->assertFalse($customerOwner->can('update demo_platform.wallet'));
        $this->assertTrue($operatorOwner->fresh()->can('update demo_platform.wallet'));
        $this->assertTrue($this->makeSuperAdmin()->can('update demo_platform.wallet'));

        // Everything else still works as before for the customer's Admin.
        $this->assertTrue($customerOwner->can('list users.user'));
    }

    public function test_operator_member_needs_the_platform_permission_itself(): void
    {
        $this->platformModules(['demo_platform']);
        [$operator] = $this->makeBusiness();
        System::addProperty('primary_business_id', $operator->id);
        $member = $this->makeMember($operator, []);

        $this->assertFalse($member->can('update demo_platform.wallet'));
    }

    public function test_customers_cannot_manage_staff_or_roles_when_switched_off(): void
    {
        [$operator, $operatorOwner] = $this->makeBusiness();
        System::addProperty('primary_business_id', $operator->id);
        System::addProperty(PlatformAccess::STAFF_SETTING, '0');
        [, $customerOwner] = $this->makeBusiness();

        $this->actingAs($customerOwner, 'staff')->get(route('users.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('users.index'), false);

        $this->flushSession();
        $this->actingAs($operatorOwner->fresh(), 'staff')->get(route('users.index'))->assertOk();
    }

    public function test_superadmin_signed_in_as_a_customer_can_still_manage_their_staff(): void
    {
        System::addProperty(PlatformAccess::STAFF_SETTING, '0');
        [, $customerOwner] = $this->makeBusiness();

        $this->actingAs($customerOwner, 'staff')
            ->withSession([ImpersonationService::SESSION_KEY => ['impersonator_id' => 1, 'impersonator_name' => 'Platform']])
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_security_tab_saves_both_settings(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.settings.index', ['tab' => 'security']))
            ->assertOk()->assertSee('name="idle_timeout_minutes"', false)->assertSee('name="business_manages_staff"', false);

        $this->put(route('superadmin.settings.update'), ['tab' => 'security', 'idle_timeout_minutes' => 2])
            ->assertRedirect(route('superadmin.settings.index', ['tab' => 'security']));

        $this->assertSame('2', System::getProperty('idle_timeout_minutes'));
        $this->assertSame('0', System::getProperty(PlatformAccess::STAFF_SETTING));
        // Saving the security tab leaves the registration switches alone.
        $this->assertNull(System::getProperty('allow_registration'));
    }

    public function test_idle_staff_are_signed_out_and_audited(): void
    {
        System::addProperty('idle_timeout_minutes', '2');
        $user = $this->makeStaff();

        $this->actingAs($user, 'staff')
            ->withSession([LogoutIdleStaff::SESSION_KEY => time() - 200])
            ->get(route('dashboard'))
            ->assertRedirect(route('staff.login'));

        $this->assertGuest('staff');
        $log = ActivityLog::query()->withoutGlobalScopes()->where('action', ActivityLogAction::Logout->value)->latest('id')->first();
        $this->assertSame('idle', $log->properties['reason'] ?? null);
    }

    public function test_activity_and_pings_keep_staff_signed_in(): void
    {
        System::addProperty('idle_timeout_minutes', '2');
        $user = $this->makeStaff();

        $this->actingAs($user, 'staff')
            ->withSession([LogoutIdleStaff::SESSION_KEY => time() - 60])
            ->post(route('staff.session.ping'))
            ->assertNoContent();

        $this->assertAuthenticatedAs($user, 'staff');
        $this->assertGreaterThan(time() - 5, session(LogoutIdleStaff::SESSION_KEY));
        $this->get(route('dashboard'))->assertOk()->assertSee('idle-logout.js', false);
    }

    public function test_passive_requests_do_not_count_as_activity(): void
    {
        System::addProperty('idle_timeout_minutes', '2');
        $this->actingAs($this->makeStaff(), 'staff');
        $stamp = time() - 90;

        $this->withSession([LogoutIdleStaff::SESSION_KEY => $stamp])
            ->withHeaders(['X-Idle-Passive' => '1'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($stamp, session(LogoutIdleStaff::SESSION_KEY));
    }

    public function test_no_timeout_by_default(): void
    {
        $this->actingAs($this->makeStaff(), 'staff')
            ->withSession([LogoutIdleStaff::SESSION_KEY => time() - 100000])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('idle-logout.js', false);
    }
}
