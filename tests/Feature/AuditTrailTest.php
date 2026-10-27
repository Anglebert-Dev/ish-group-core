<?php

namespace Tests\Feature;

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog\ActivityLog;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    private function logged(ActivityLogAction $action, ?string $contains = null): bool
    {
        return ActivityLog::query()->withoutGlobalScopes()
            ->where('action', $action->value)
            ->when($contains, fn ($q) => $q->where('description', 'like', '%'.$contains.'%'))
            ->exists();
    }

    public function test_sign_in_sign_out_and_failures_are_logged(): void
    {
        $this->makeStaff(['email' => 'audit@example.com', 'name' => 'Audit Person']);

        $this->post(route('staff.login.store'), ['email' => 'audit@example.com', 'password' => 'WrongPass1!x']);
        $this->assertTrue($this->logged(ActivityLogAction::LoginFailed, 'audit@example.com'));

        $this->post(route('staff.login.store'), ['email' => 'audit@example.com', 'password' => self::TEST_PASSWORD]);
        $this->assertTrue($this->logged(ActivityLogAction::Login, 'Audit Person'));
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScopes()->where('action', 'login')->count(), 'one entry per sign-in');
        $this->assertFalse(ActivityLog::query()->withoutGlobalScopes()->where('action', 'updated')->where('subject_type', (new \App\Models\User)->getMorphClass())->exists(), 'last_login is not noise');

        $this->post(route('staff.logout'));
        $this->assertTrue($this->logged(ActivityLogAction::Logout, 'Audit Person'));
    }

    public function test_password_role_and_permission_changes_are_logged(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $this->actingAs($owner, 'staff');
        app(PermissionService::class)->initPermissions(true);

        $this->withConfirmedPassword()->post(route('roles.store'), ['name' => 'Cashier', 'permissions' => ['list users.user']]);
        $this->assertTrue($this->logged(ActivityLogAction::PermissionsChanged, 'Cashier'));

        $role = \App\Models\Role\Role::query()->where('name', 'Cashier#'.$business->id)->first();
        $this->withConfirmedPassword()->post(route('users.store'), [
            'name' => 'New Cashier', 'email' => 'cashier@example.com',
            'password' => self::TEST_PASSWORD, 'password_confirmation' => self::TEST_PASSWORD, 'role' => $role->id,
        ]);
        $this->assertTrue($this->logged(ActivityLogAction::RoleChanged, 'New Cashier'));

        $this->put(route('staff.profile.password'), [
            'current_password' => self::TEST_PASSWORD, 'password' => 'AnotherPass1!x', 'password_confirmation' => 'AnotherPass1!x',
        ]);
        $this->assertTrue($this->logged(ActivityLogAction::PasswordChanged));
    }

    public function test_platform_events_are_logged_and_visible_to_superadmin_only(): void
    {
        $super = $this->makeSuperAdmin();
        [$client, $owner] = $this->makeBusiness(['business_name' => 'Client Co', 'name' => 'Client Owner']);
        $this->actingAs($super, 'staff');

        app(ModuleManager::class)->install('Frontend');
        $this->assertTrue($this->logged(ActivityLogAction::ModuleChanged, 'Frontend'));

        $this->get(route('users.export.excel'));
        $this->assertTrue($this->logged(ActivityLogAction::Exported));

        $this->withConfirmedPassword()->post(route('superadmin.impersonate', $owner->uuid));
        $this->assertTrue($this->logged(ActivityLogAction::ImpersonationStarted, 'Client Owner'));
        $this->assertFalse(ActivityLog::query()->withoutGlobalScopes()->where('action', 'login')->where('description', 'like', '%Client Owner%')->exists(), 'not a normal sign-in');
        $this->post(route('impersonation.leave'));
        $this->assertTrue($this->logged(ActivityLogAction::ImpersonationEnded));

        // Platform audit shows every business; filters work.
        $this->get(route('superadmin.audit.index'))->assertOk()->assertSee('Client Owner');
        $this->get(route('superadmin.audit.index', ['action' => 'module_changed']))->assertOk()->assertSee('Frontend')->assertDontSee('Client Owner');
        $entry = ActivityLog::query()->withoutGlobalScopes()->where('business_id', $client->id)->first();
        $this->get(route('superadmin.audit.show', $entry->uuid))->assertOk();
        $this->get(route('superadmin.audit.export', ['excel']))->assertOk();

        // A business admin sees only their own business.
        $this->flushSession();
        $this->actingAs($owner, 'staff');
        $this->get(route('superadmin.audit.index'))->assertForbidden();
        $this->get(route('activity-logs.index'))->assertOk()->assertDontSee('Module Frontend installed');
        $this->get(route('activity-logs.index', ['action' => 'impersonation_started']))->assertOk()->assertSee('Client Owner');
    }

    public function test_module_actions_via_helper_are_scoped_to_their_business(): void
    {
        [$alpha, $alphaOwner] = $this->makeBusiness();
        [$bravo, $bravoOwner] = $this->makeBusiness();

        $this->actingAs($alphaOwner, 'staff');
        audit('sale_completed', 'Sale #100 completed', null, ['total' => 5000]);

        $entry = ActivityLog::query()->withoutGlobalScopes()->where('action', 'sale_completed')->firstOrFail();
        $this->assertSame($alpha->id, $entry->business_id);
        $this->assertSame('Sale Completed', $entry->actionLabel());

        $this->get(route('activity-logs.index', ['action' => 'sale_completed']))->assertOk()->assertSee('Sale #100 completed');

        $this->flushSession();
        $this->actingAs($bravoOwner, 'staff');
        $this->get(route('activity-logs.index'))->assertOk()->assertDontSee('Sale #100 completed');

        $super = $this->makeSuperAdmin();
        $this->flushSession();
        $this->actingAs($super, 'staff');
        $this->get(route('superadmin.audit.index', ['business' => $alpha->id]))->assertOk()->assertSee('Sale #100 completed');
        $this->get(route('superadmin.audit.index', ['business' => $bravo->id]))->assertOk()->assertDontSee('Sale #100 completed');
    }

    public function test_audit_can_be_sorted(): void
    {
        [, $owner] = $this->makeBusiness();
        $this->actingAs($owner, 'staff');
        audit('zeta_event', 'Zeta happened');
        audit('alpha_event', 'Alpha happened');

        $this->get(route('activity-logs.index', ['sort' => 'action', 'dir' => 'asc']))
            ->assertOk()->assertSeeInOrder(['Alpha happened', 'Zeta happened']);
        $this->get(route('activity-logs.index', ['sort' => 'action', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Zeta happened', 'Alpha happened']);

        $this->flushSession();
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->get(route('superadmin.audit.index', ['sort' => 'business', 'dir' => 'asc']))->assertOk();
    }
}
