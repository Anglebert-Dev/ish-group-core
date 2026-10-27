<?php

namespace Modules\Frontend\Tests\Feature;

use App\Models\System\System;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Hash;
use Modules\Frontend\Enums\CustomerStatus;
use Modules\Frontend\Models\Customer;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Frontend');
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        $customer = new Customer([
            'name' => $overrides['name'] ?? 'Jane Customer',
            'email' => $overrides['email'] ?? 'jane@example.com',
            'phone' => $overrides['phone'] ?? '+250788000000',
            'password' => Hash::make($overrides['password'] ?? self::TEST_PASSWORD),
        ]);
        $customer->status = $overrides['status'] ?? CustomerStatus::Active;
        $customer->save();

        return $customer->fresh();
    }

    public function test_root_shows_the_public_home_page(): void
    {
        [$business] = $this->makeBusiness(['business_name' => 'Public Brand']);
        System::addProperty('primary_business_id', $business->id);

        $this->get('/')->assertOk()->assertSee('Public Brand');
    }

    public function test_marketing_pages_load(): void
    {
        foreach (['about', 'contact', 'services', 'privacy', 'terms', 'login', 'register'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_staff_login_page_links_to_customer_login(): void
    {
        $this->get(route('staff.login'))->assertOk()->assertSee(route('login'), false);
    }

    public function test_customer_can_register_and_is_logged_in(): void
    {
        [$business] = $this->makeBusiness();
        System::addProperty('primary_business_id', $business->id);

        $this->post(route('register.store'), [
            'name' => 'New Customer',
            'email' => 'NEW@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('profile'));

        $customer = Customer::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame($business->id, $customer->business_id);
    }

    public function test_customer_can_log_in_by_email_or_phone_and_open_account(): void
    {
        $this->makeCustomer(['email' => 'login@example.com', 'phone' => '+250788123456']);

        $this->post(route('login.store'), ['login' => 'login@example.com', 'password' => self::TEST_PASSWORD])
            ->assertRedirect(route('profile'));
        $this->get(route('profile'))->assertOk();
        $this->get(route('account.profile'))->assertOk();

        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => '0788 123 456', 'password' => self::TEST_PASSWORD])
            ->assertSessionHasErrors();
        $this->post(route('login.store'), ['login' => '+250 788 123 456', 'password' => self::TEST_PASSWORD])
            ->assertRedirect(route('profile'));
    }

    public function test_customer_can_change_password(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($customer, 'customer');

        $this->put(route('profile.password'), [
            'current_password' => self::TEST_PASSWORD,
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecurePass1!', $customer->fresh()->password));
    }

    public function test_banned_customer_cannot_log_in_and_live_session_ends(): void
    {
        $customer = $this->makeCustomer(['email' => 'banned@example.com', 'status' => CustomerStatus::Banned]);

        $this->post(route('login.store'), ['login' => 'banned@example.com', 'password' => self::TEST_PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertGuest('customer');

        $customer->forceFill(['status' => CustomerStatus::Active])->save();
        $this->actingAs($customer, 'customer');
        $customer->forceFill(['status' => CustomerStatus::Banned])->save();

        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->assertGuest('customer');
    }

    public function test_customer_reset_link_points_at_customer_reset_page(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $customer = $this->makeCustomer(['email' => 'reset@example.com']);

        $this->post(route('customer.password.email'), ['email' => 'reset@example.com'])
            ->assertSessionHas('success', __('body.password_reset_link_sent'));

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $customer,
            \Illuminate\Auth\Notifications\ResetPassword::class,
            fn ($notification) => str_contains($notification->toMail($customer)->actionUrl, '/reset-password/')
                && ! str_contains($notification->toMail($customer)->actionUrl, '/app/'),
        );
    }

    public function test_customer_and_staff_sessions_are_exclusive(): void
    {
        $this->makeStaff(['email' => 'staff-portal@example.com']);
        $this->makeCustomer(['email' => 'customer-portal@example.com']);

        $this->post(route('staff.login.store'), ['email' => 'staff-portal@example.com', 'password' => self::TEST_PASSWORD]);
        $this->assertAuthenticated('staff');

        $this->post(route('login.store'), ['login' => 'customer-portal@example.com', 'password' => self::TEST_PASSWORD])
            ->assertRedirect(route('profile'));

        $this->assertAuthenticated('customer');
        $this->assertGuest('staff');
    }

    public function test_guest_is_sent_to_customer_login_from_account_pages(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }
}
