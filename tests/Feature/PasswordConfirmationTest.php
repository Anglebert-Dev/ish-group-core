<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Http\Middleware\ConfirmStaffPassword;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    public function test_action_is_resumed_after_confirming_without_clicking_again(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $member = $this->makeMember($business);
        $this->actingAs($owner, 'staff');

        $this->from(route('users.index'))
            ->put(route('users.status', $member), ['status' => UserStatus::Banned->value])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas(ConfirmStaffPassword::MODAL_FLAG);

        // The page the user is sent back to opens the password pop-up.
        $this->get(route('users.index'))->assertSee('id="password-confirm-modal" class="fixed inset-0 z-[70] "', false);

        $this->assertSame(UserStatus::Active, $member->fresh()->status, 'nothing happens before confirming');

        $this->post(route('staff.password.confirm.store'), ['password' => self::TEST_PASSWORD])
            ->assertOk()
            ->assertViewIs('auth.confirm-password-resume')
            ->assertSee('action="'.route('users.status', $member).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="status" value="banned"', false);

        $this->assertNull(session(ConfirmStaffPassword::PENDING_KEY));
        $this->assertSame(route('users.index'), session('_previous.url'));

        // The auto-submitted form now goes straight through.
        $this->put(route('users.status', $member), ['status' => UserStatus::Banned->value])
            ->assertRedirect(route('users.index'));

        $this->assertSame(UserStatus::Banned, $member->fresh()->status);
    }

    public function test_confirm_page_is_shown_inside_the_app(): void
    {
        $this->actingAsStaff();

        $this->get(route('staff.password.confirm'))
            ->assertOk()
            ->assertSee('id="dashboard-sidebar"', false)
            ->assertSee(route('staff.password.confirm.store'), false);
    }

    public function test_array_inputs_are_carried_over(): void
    {
        $this->actingAsStaff();

        $this->post(route('roles.store'), ['name' => 'Auditor', 'permissions' => ['list users.user', 'read users.user']])
            ->assertRedirect(route('staff.password.confirm'));  // no referer: full page fallback

        $this->post(route('staff.password.confirm.store'), ['password' => self::TEST_PASSWORD])
            ->assertSee('name="name" value="Auditor"', false)
            ->assertSee('name="permissions[0]" value="list users.user"', false)
            ->assertSee('name="permissions[1]" value="read users.user"', false);
    }

    public function test_pop_up_confirms_over_json_and_returns_the_action(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $member = $this->makeMember($business);
        $this->actingAs($owner, 'staff');

        $this->from(route('users.index'))->put(route('users.status', $member), ['status' => UserStatus::Banned->value]);

        $this->postJson(route('staff.password.confirm.store'), ['password' => 'WrongPass1!'])
            ->assertStatus(422)->assertJsonPath('message', __('body.incorrect_current_password'));

        $this->postJson(route('staff.password.confirm.store'), ['password' => self::TEST_PASSWORD])
            ->assertOk()
            ->assertJsonPath('action.method', 'PUT')
            ->assertJsonPath('action.url', route('users.status', $member))
            ->assertJsonPath('action.fields.0', ['status', 'banned']);
    }

    public function test_wrong_password_keeps_the_action_pending(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $member = $this->makeMember($business);
        $this->actingAs($owner, 'staff');

        $this->put(route('users.status', $member), ['status' => UserStatus::Banned->value]);

        $this->post(route('staff.password.confirm.store'), ['password' => 'WrongPass1!'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull(session(ConfirmStaffPassword::PENDING_KEY));
    }

    public function test_expired_pending_action_falls_back_to_plain_redirect(): void
    {
        $this->actingAsStaff();
        $this->withSession([ConfirmStaffPassword::PENDING_KEY => [
            'method' => 'POST', 'url' => route('roles.store'), 'input' => encrypt([]),
            'back' => route('roles.index'), 'at' => time() - ConfirmStaffPassword::PENDING_TTL - 1,
        ]]);

        $this->post(route('staff.password.confirm.store'), ['password' => self::TEST_PASSWORD])
            ->assertRedirect(route('dashboard'));
    }
}
