<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExclusivePortalAuthTest extends TestCase
{
    public function test_customer_login_logs_out_staff_session(): void
    {
        $this->makeStaff(['email' => 'staff-portal@example.com']);
        $this->makeCustomer(['email' => 'customer-portal@example.com']);

        $this->post(route('staff.login.store'), [
            'email' => 'staff-portal@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('staff');

        $this->post(route('login.store'), [
            'login' => 'customer-portal@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('profile'));

        $this->assertAuthenticated('customer');
        $this->assertGuest('staff');
        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
        $this->get(route('profile'))->assertOk();
    }

    public function test_staff_login_logs_out_customer_session(): void
    {
        $this->makeStaff(['email' => 'staff-switch@example.com']);
        $this->makeCustomer(['email' => 'customer-switch@example.com']);

        $this->post(route('login.store'), [
            'login' => 'customer-switch@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('profile'));

        $this->assertAuthenticated('customer');

        $this->post(route('staff.login.store'), [
            'email' => 'staff-switch@example.com',
            'password' => 'SecurePass1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('staff');
        $this->assertGuest('customer');
        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertOk();
    }
}
