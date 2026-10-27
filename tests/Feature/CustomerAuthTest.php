<?php

namespace Tests\Feature;

use App\Enums\CustomerAccountType;
use App\Enums\CustomerStatus;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    public function test_personal_customer_can_register_and_is_logged_in(): void
    {
        $this->get(route('register'))->assertOk();

        $this->post(route('register.store'), [
            'account_type' => CustomerAccountType::Personal->value,
            'name' => 'Personal Renter',
            'email' => 'personal@example.com',
            'phone' => '+250788111111',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertRedirect(route('profile'));

        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', [
            'email' => 'personal@example.com',
            'status' => CustomerStatus::Active->value,
        ]);
        $this->assertDatabaseCount('corporate_customers', 0);
    }

    public function test_organization_customer_creates_a_corporate_account(): void
    {
        $this->post(route('register.store'), [
            'account_type' => CustomerAccountType::Organization->value,
            'name' => 'Org Contact',
            'email' => 'org@example.com',
            'phone' => '+250788222222',
            'company_name' => 'Acme Logistics',
            'tin' => '123456789',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertRedirect(route('profile'));

        $customer = Customer::query()->where('email', 'org@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertTrue($customer->isCorporate());
        $this->assertDatabaseHas('corporate_customers', [
            'company_name' => 'Acme Logistics',
            'tin' => '123456789',
        ]);
        $this->assertTrue(CorporateCustomer::query()->where('company_name', 'Acme Logistics')->exists());
    }

    public function test_organization_registration_requires_company_name(): void
    {
        $this->post(route('register.store'), [
            'account_type' => CustomerAccountType::Organization->value,
            'name' => 'Org Contact',
            'email' => 'org2@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertSessionHasErrors('company_name');
    }

    public function test_customer_can_log_in_and_open_account_pages(): void
    {
        $this->makeCustomer(['email' => 'login-customer@example.com']);

        $this->post(route('login.store'), [
            'login' => 'login-customer@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('profile'));

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee(__('body.book_a_car'), false)
            ->assertSee(__('body.quick_actions'), false)
            ->assertSee(__('body.do_this_next'), false)
            ->assertSee(__('body.account_overview'), false)
            ->assertSee(__('body.back_to_website'), false)
            ->assertSee('id="account-sidebar"', false)
            ->assertSee('data-user-toggle', false);
        $this->get(route('account.profile'))
            ->assertOk()
            ->assertSee(__('body.profile'), false)
            ->assertSee(__('body.change_password'), false);
        $this->get(route('account.bookings.index'))->assertOk();
    }

    public function test_customer_remember_me_sets_a_recaller_cookie(): void
    {
        $this->makeCustomer(['email' => 'remember-customer@example.com']);

        $response = $this->post(route('login.store'), [
            'login' => 'remember-customer@example.com',
            'password' => self::TEST_PASSWORD,
            'remember' => '1',
        ]);

        $response->assertRedirect(route('profile'));
        $this->assertAuthenticated('customer');
        $this->assertNotEmpty(
            Customer::query()->where('email', 'remember-customer@example.com')->value('remember_token')
        );
        $response->assertCookie(auth()->guard('customer')->getRecallerName());
    }

    public function test_customer_can_change_password(): void
    {
        $this->actingAsCustomer($this->makeCustomer(['email' => 'pwd-customer@example.com']));

        $this->put(route('profile.password'), [
            'current_password' => 'SecurePass1!',
            'password' => 'SecurePass2!',
            'password_confirmation' => 'SecurePass2!',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'login' => 'pwd-customer@example.com',
            'password' => 'SecurePass2!',
        ])->assertRedirect(route('profile'));
    }

    public function test_customer_password_change_rejects_wrong_current_password(): void
    {
        $this->actingAsCustomer($this->makeCustomer(['email' => 'pwd-wrong@example.com']));

        $this->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'SecurePass2!',
            'password_confirmation' => 'SecurePass2!',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_banned_customer_cannot_log_in(): void
    {
        $this->makeCustomer([
            'email' => 'banned@example.com',
            'status' => CustomerStatus::Banned,
        ]);

        $this->post(route('login.store'), [
            'login' => 'banned@example.com',
            'password' => 'SecurePass1!',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('customer');
    }

    public function test_staff_creates_customer_without_password_then_generates_portal_account(): void
    {
        $this->actingAsStaff();

        $this->post(route('customers.store'), [
            'name' => 'Walk-in Guest',
            'email' => 'walkin@example.com',
            'phone' => '+250788999888',
            'national_id' => '1199000011223344',
            'address' => 'Kigali, Rwanda',
            'driver_license_number' => 'DL-WALKIN-1',
        ])->assertRedirect();

        $customer = Customer::query()->where('email', 'walkin@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame('1199000011223344', $customer->national_id);
        $this->assertSame('DL-WALKIN-1', $customer->driver_license_number);
        $this->assertFalse($customer->hasPortalPassword());

        $this->post(route('login.store'), [
            'login' => 'walkin@example.com',
            'password' => 'anything',
        ])->assertSessionHasErrors('login');

        $this->post(route('customers.portal-account', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('portal_credentials');

        $credentials = session('portal_credentials');
        $this->assertNotEmpty($credentials['password'] ?? null);
        $this->assertTrue($customer->fresh()->hasPortalPassword());

        $this->post(route('login.store'), [
            'login' => '+250788999888',
            'password' => $credentials['password'],
        ])->assertRedirect(route('profile'));

        $this->assertAuthenticated('customer');
    }

    public function test_staff_can_reset_customer_portal_password(): void
    {
        $this->actingAsStaff();
        $customer = $this->makeCustomer(['email' => 'reset-me@example.com']);

        $this->post(route('customers.reset-password', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('portal_credentials');

        $newPassword = session('portal_credentials')['password'];

        $this->post(route('login.store'), [
            'login' => 'reset-me@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertSessionHasErrors('login');

        $this->post(route('login.store'), [
            'login' => 'reset-me@example.com',
            'password' => $newPassword,
        ])->assertRedirect(route('profile'));
    }

    public function test_authenticated_customer_stays_on_customer_portal_from_forgot_password(): void
    {
        $this->actingAsCustomer($this->makeCustomer(['email' => 'already-in@example.com']));

        $this->get(route('customer.password.request'))
            ->assertRedirect(route('profile'));
        $this->get(route('login'))
            ->assertRedirect(route('profile'));
    }

    public function test_customer_can_reset_password_from_email_link(): void
    {
        Notification::fake();

        $customer = $this->makeCustomer(['email' => 'Forgot.Me@example.com']);

        $this->post(route('customer.password.email'), [
            'email' => 'forgot.me@example.com',
        ])->assertRedirect()->assertSessionHas('success', __('body.password_reset_link_sent'));

        $token = null;
        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotEmpty($token);

        $this->post(route('customer.password.update'), [
            'token' => $token,
            'email' => 'forgot.me@example.com',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])->assertRedirect(route('login'))->assertSessionHas('success', __('body.password_reset_success'));

        $this->post(route('login.store'), [
            'login' => 'forgot.me@example.com',
            'password' => self::TEST_PASSWORD,
        ])->assertSessionHasErrors('login');

        $this->post(route('login.store'), [
            'login' => 'forgot.me@example.com',
            'password' => 'NewSecurePass1!',
        ])->assertRedirect(route('profile'));
    }

    public function test_staff_created_customer_can_set_password_via_reset_link(): void
    {
        Notification::fake();

        $customer = $this->makeCustomer(['email' => 'walkin-reset@example.com']);
        $customer->forceFill(['password' => null])->save();
        $this->assertFalse($customer->fresh()->hasPortalPassword());

        $this->post(route('customer.password.email'), [
            'email' => 'walkin-reset@example.com',
        ])->assertRedirect()->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('customer.password.update'), [
            'token' => $token,
            'email' => 'walkin-reset@example.com',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'login' => 'walkin-reset@example.com',
            'password' => 'NewSecurePass1!',
        ])->assertRedirect(route('profile'));
    }
}
