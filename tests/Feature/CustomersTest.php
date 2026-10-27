<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Customer\Customer;
use App\Notifications\BrandedNotification;
use App\Services\Module\ModuleManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Modules\Frontend\Services\SiteService;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'company',
            'name' => 'Aline',
            'company_name' => 'Kigali Uniforms Ltd',
            'contact_person' => 'Aline Uwase',
            'email' => 'orders@uniforms.rw',
            'phone' => '+250788123456',
            'tax_id' => '101234567',
            'address' => 'KN 5 Rd, Kigali',
            'payment_terms_days' => 30,
        ];
    }

    public function test_staff_create_view_and_edit_a_customer(): void
    {
        $owner = $this->actingAsStaff();

        $this->post(route('customers.store'), $this->payload(['email' => 'Orders@Uniforms.rw']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $customer = Customer::query()->firstOrFail();
        $this->assertSame($owner->business_id, $customer->business_id);
        $this->assertSame('orders@uniforms.rw', $customer->email);
        $this->assertSame(CustomerStatus::Active, $customer->status);
        $this->assertSame('Kigali Uniforms Ltd', $customer->displayName());
        $this->assertFalse($customer->hasPortalPassword());

        $this->get(route('customers.index'))->assertOk()->assertSee('Kigali Uniforms Ltd');
        $this->get(route('customers.show', $customer))->assertOk()->assertSee('101234567');
        $this->get(route('customers.edit', $customer))->assertOk();
        $this->get(route('customers.export.pdf'))->assertOk();
        $this->get(route('customers.export.excel'))->assertOk();

        $this->put(route('customers.update', $customer), $this->payload(['company_name' => 'Kigali Uniforms Group']))
            ->assertSessionHas('success');
        $this->assertSame('Kigali Uniforms Group', $customer->fresh()->company_name);
    }

    public function test_a_customer_without_email_is_allowed(): void
    {
        $this->actingAsStaff();

        $this->post(route('customers.store'), $this->payload(['type' => 'person', 'company_name' => null, 'email' => null]))
            ->assertSessionHasNoErrors();
        $this->post(route('customers.store'), $this->payload(['type' => 'person', 'company_name' => null, 'email' => null, 'name' => 'Eric']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::query()->count());
    }

    public function test_company_needs_a_company_name(): void
    {
        $this->actingAsStaff();

        $this->post(route('customers.store'), $this->payload(['company_name' => '']))
            ->assertSessionHasErrors('company_name');
    }

    public function test_email_is_unique_within_a_business_only(): void
    {
        [, $first] = $this->makeBusiness();
        [, $second] = $this->makeBusiness();

        $this->actingAs($first, 'staff');
        $this->post(route('customers.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('customers.store'), $this->payload(['email' => 'ORDERS@uniforms.rw']))
            ->assertSessionHasErrors('email');

        $this->flushSession();
        $this->actingAs($second, 'staff');
        $this->post(route('customers.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::query()->withoutGlobalScopes()->count());
    }

    public function test_businesses_only_see_their_own_customers(): void
    {
        [, $owner] = $this->makeBusiness();
        [$other] = $this->makeBusiness();

        $theirs = new Customer(['type' => 'person', 'name' => 'Theirs Person']);
        $theirs->business_id = $other->id;
        $theirs->status = CustomerStatus::Active;
        $theirs->save();

        $this->actingAs($owner, 'staff');

        $this->get(route('customers.index'))->assertOk()->assertDontSee('Theirs Person');
        $this->get(route('customers.show', $theirs))->assertNotFound();
        $this->put(route('customers.status', $theirs), ['status' => 'banned'])->assertNotFound();
    }

    public function test_members_need_the_customer_permission(): void
    {
        [$business] = $this->makeBusiness();

        $this->actingAs($this->makeMember($business), 'staff');
        $this->get(route('customers.index'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->makeMember($business, ['list customers.customer']), 'staff');
        $this->get(route('customers.index'))->assertOk();
        $this->get(route('customers.create'))->assertForbidden();
    }

    public function test_status_change_is_audited(): void
    {
        $owner = $this->actingAsStaff();
        $this->post(route('customers.store'), $this->payload());
        $customer = Customer::query()->firstOrFail();

        $this->put(route('customers.status', $customer), ['status' => 'inactive'])->assertSessionHas('success');

        $this->assertSame(CustomerStatus::Inactive, $customer->fresh()->status);
        $this->assertTrue(ActivityLog::query()
            ->where('business_id', $owner->business_id)
            ->where('description', 'Kigali Uniforms Ltd changed from Active to Inactive')
            ->exists());
    }

    public function test_delete_needs_password_and_is_refused_when_modules_report_links(): void
    {
        $this->actingAsStaff();
        $this->post(route('customers.store'), $this->payload());
        $customer = Customer::query()->firstOrFail();

        $this->delete(route('customers.destroy', $customer))->assertRedirect();
        $this->assertNotNull($customer->fresh());

        $this->partialMock(ModuleManager::class, function ($mock) {
            $mock->shouldReceive('hook')->with('customer_links', Mockery::any())->andReturn(['SalesOrders' => 3]);
        });

        $this->withConfirmedPassword()->delete(route('customers.destroy', $customer))->assertSessionHas('error');
        $this->assertNotNull($customer->fresh());
    }

    public function test_delete_works_without_links(): void
    {
        $this->actingAsStaff();
        $this->post(route('customers.store'), $this->payload());
        $customer = Customer::query()->firstOrFail();

        $this->withConfirmedPassword()->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);
    }

    public function test_website_access_is_offered_only_with_a_login_module(): void
    {
        $this->actingAsStaff();
        $this->post(route('customers.store'), $this->payload());
        $customer = Customer::query()->firstOrFail();

        $this->get(route('customers.show', $customer))->assertOk()->assertDontSee(__('body.customer_send_invite'));
        $this->post(route('customers.portal.invite', $customer))->assertSessionHas('error');
    }

    public function test_staff_can_invite_a_customer_and_remove_access(): void
    {
        Notification::fake();
        app(ModuleManager::class)->install('Frontend');

        $owner = $this->actingAsStaff();
        $site = app(SiteService::class)->forBusiness($owner->business);
        $site->forceFill(['content' => ['general' => ['allow_login' => true]]])->save();
        $this->post(route('customers.store'), $this->payload());
        $customer = Customer::query()->firstOrFail();

        $this->get(route('customers.show', $customer))->assertSee(__('body.customer_send_invite'));
        $this->post(route('customers.portal.invite', $customer))->assertSessionHas('success');
        Notification::assertNotSentTo($customer, ResetPassword::class);

        $url = null;
        Notification::assertSentTo($customer, BrandedNotification::class, function ($n) use (&$url, $owner) {
            $url = $n->actionUrl;

            return $n->subject === "You're invited to {$owner->business->name}" && str_contains($url, '&welcome=1');
        });

        // The invitation outlives the 60-minute reset window.
        $this->travel(2)->days();
        $this->flushSession();
        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path.'?'.parse_url($url, PHP_URL_QUERY))->assertOk()->assertSee(__('body.auth_welcome_title'));
        $this->post('/s/'.$site->slug.'/reset-password', [
            'token' => basename($path),
            'email' => $customer->email,
            'password' => 'Fresh-Customer-Pass-26!',
            'password_confirmation' => 'Fresh-Customer-Pass-26!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->hasPortalPassword());

        $this->actingAs($owner, 'staff');
        $this->post(route('customers.portal.invite', $customer))->assertSessionHas('success');
        Notification::assertSentTo($customer, ResetPassword::class);

        $this->withConfirmedPassword()->delete(route('customers.portal.revoke', $customer))->assertSessionHas('success');
        $this->assertFalse($customer->fresh()->hasPortalPassword());
    }
}
