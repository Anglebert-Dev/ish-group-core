<?php

namespace Modules\Frontend\Tests\Feature;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Services\Module\ModuleManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Frontend\Enums\CustomerStatus;
use Modules\Frontend\Models\Customer;
use Modules\Frontend\Models\Site;
use Modules\Frontend\Services\SiteService;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Frontend');
    }

    private function publishedSite(?Business $business = null, array $content = []): Site
    {
        $business ??= $this->makeBusiness()[0];
        $site = app(SiteService::class)->forBusiness($business);
        $site->forceFill(['is_published' => true, 'content' => $content ?: null])->save();

        return $site->fresh('business');
    }

    private function makeCustomer(Business $business, array $overrides = []): Customer
    {
        $customer = new Customer([
            'name' => $overrides['name'] ?? 'Jane Customer',
            'email' => $overrides['email'] ?? 'jane@example.com',
            'phone' => $overrides['phone'] ?? '+250788000000',
            'password' => Hash::make($overrides['password'] ?? self::TEST_PASSWORD),
        ]);
        $customer->business_id = $business->id;
        $customer->status = $overrides['status'] ?? CustomerStatus::Active;
        $customer->save();

        return $customer->fresh();
    }

    public function test_every_business_gets_its_own_site_at_its_slug(): void
    {
        [$alpha] = $this->makeBusiness(['business_name' => 'Alpha Shop']);
        [$bravo] = $this->makeBusiness(['business_name' => 'Bravo Studio']);
        $this->publishedSite($alpha);
        $this->publishedSite($bravo, ['home' => ['hero' => ['title' => 'Bravo makes things']]]);

        $this->get('/s/alpha-shop')->assertOk()->assertSee('Alpha Shop')->assertDontSee('Bravo makes things');
        $this->get('/s/bravo-studio')->assertOk()->assertSee('Bravo makes things');
        $this->get('/s/alpha-shop/about')->assertOk()->assertSee('/s/alpha-shop/contact', false);
    }

    public function test_marketing_and_auth_pages_load(): void
    {
        $site = $this->publishedSite();

        foreach (['', '/about', '/services', '/contact', '/privacy', '/terms', '/login', '/register'] as $path) {
            $this->get('/s/'.$site->slug.$path)->assertOk();
        }
    }

    public function test_unpublished_site_is_hidden_except_for_own_staff(): void
    {
        [$business, $owner] = $this->makeBusiness(['business_name' => 'Draft Co']);
        $site = app(SiteService::class)->forBusiness($business);

        $this->get('/s/'.$site->slug)->assertNotFound();

        $this->actingAs($owner, 'staff');
        $this->get('/s/'.$site->slug)->assertOk();

        $this->actingAs($this->makeStaff(), 'staff');
        $this->get('/s/'.$site->slug)->assertNotFound();
    }

    public function test_staff_logged_in_through_the_form_can_preview_and_use_view_website(): void
    {
        [$business] = $this->makeBusiness(['business_name' => 'Preview Co', 'email' => 'preview@example.com']);
        $site = app(SiteService::class)->forBusiness($business);

        // A real login (session), not actingAs().
        $this->post(route('staff.login.store'), ['email' => 'preview@example.com', 'password' => self::TEST_PASSWORD]);

        $this->get(route('frontend.admin.website.view'))->assertRedirect('/s/'.$site->slug);
        $this->get('/s/'.$site->slug)->assertOk()->assertSee('Preview Co');
    }

    public function test_root_shows_primary_site_once_published(): void
    {
        [$business] = $this->makeBusiness(['business_name' => 'Primary Brand']);
        System::addProperty('primary_business_id', $business->id);

        $this->get('/')->assertRedirect(route('staff.login'));

        $this->publishedSite($business);
        $this->get('/')->assertOk()->assertSee('Primary Brand');
    }

    public function test_sections_and_menu_follow_saved_content(): void
    {
        $site = $this->publishedSite(null, [
            'home' => ['features' => ['show' => false], 'hero' => ['title' => 'Custom hero']],
            'header' => ['show_services' => false],
            'pages' => ['services' => ['items' => [['title' => 'Only service', 'description' => 'Just one']]]],
        ]);

        $this->get('/s/'.$site->slug)
            ->assertOk()
            ->assertSee('Custom hero')
            ->assertDontSee('Built to be simple')
            ->assertDontSee('/s/'.$site->slug.'/services', false);

        $this->get('/s/'.$site->slug.'/services')
            ->assertSee('Only service')
            ->assertDontSee('Service two');
    }

    public function test_staff_sidebar_has_a_website_section(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Website')
            ->assertSee(route('frontend.admin.website.edit'), false)
            ->assertSee(route('frontend.admin.customers.index'), false);
    }

    public function test_website_section_is_hidden_when_module_is_off_for_the_business(): void
    {
        [$business, $owner] = $this->makeBusiness();
        $business->forceFill(['enabled_modules' => []])->save();
        $this->actingAs($owner, 'staff');

        $this->get(route('dashboard'))->assertDontSee(route('frontend.admin.website.edit'), false);
        $this->get(route('frontend.admin.website.edit'))->assertNotFound();
    }

    public function test_admin_edits_every_tab(): void
    {
        $owner = $this->actingAsStaff();

        foreach (['general', 'home', 'pages', 'layout'] as $tab) {
            $this->get(route('frontend.admin.website.edit', $tab))->assertOk();
        }

        $this->put(route('frontend.admin.website.update', 'general'), [
            'slug' => 'my-place',
            'is_published' => '1',
            'general' => ['seo_title' => 'My Place', 'seo_description' => 'Hello', 'allow_registration' => '0'],
            'contact' => ['email' => 'hi@example.com', 'phone' => '+250 700 000 000', 'address' => 'Main street'],
        ])->assertRedirect(route('frontend.admin.website.edit', 'general'));

        $this->put(route('frontend.admin.website.update', 'home'), [
            'home' => [
                'hero' => ['show' => '1', 'title' => 'Welcome to :company'],
                'features' => ['show' => '1', 'items' => [['title' => 'Fast', 'description' => 'Quick'], ['title' => '', 'description' => '']]],
            ],
        ])->assertSessionHas('success');

        $this->put(route('frontend.admin.website.update', 'layout'), [
            'header' => ['show_about' => '0', 'show_services' => '1', 'show_contact' => '1', 'show_account' => '1'],
            'footer' => ['blurb' => 'Footer words', 'social' => ['instagram' => 'https://instagram.com/myplace']],
        ])->assertSessionHas('success');

        $site = app(SiteService::class)->forBusiness($owner->business);
        $this->assertSame('my-place', $site->slug);
        $this->assertTrue($site->is_published);
        $this->assertFalse($site->get('general.allow_registration'));
        $this->assertSame('Welcome to '.$owner->business->name, $site->get('home.hero.title'));
        $this->assertSame([['title' => 'Fast', 'description' => 'Quick']], $site->get('home.features.items'), 'blank rows dropped');
        $this->assertSame('Welcome to :company', $site->raw('home.hero.title'), 'placeholder kept for editing');

        $this->get('/s/my-place')
            ->assertOk()
            ->assertSee('hi@example.com')
            ->assertSee('Footer words')
            ->assertSee('https://instagram.com/myplace', false)
            ->assertDontSee('/s/my-place/register', false);
        $this->get('/s/my-place/register')->assertNotFound();
    }

    public function test_slug_must_be_unique_and_not_reserved(): void
    {
        [$other] = $this->makeBusiness();
        app(SiteService::class)->forBusiness($other)->forceFill(['slug' => 'taken'])->save();

        $this->actingAsStaff();

        $this->put(route('frontend.admin.website.update', 'general'), ['slug' => 'taken'])->assertSessionHasErrors('slug');
        $this->put(route('frontend.admin.website.update', 'general'), ['slug' => 'app'])->assertSessionHasErrors('slug');
        $this->put(route('frontend.admin.website.update', 'general'), ['slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');
    }

    public function test_customer_registers_on_a_site_and_belongs_to_that_business(): void
    {
        $site = $this->publishedSite();

        $this->post('/s/'.$site->slug.'/register', [
            'name' => 'New Customer',
            'email' => 'NEW@example.com',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect('/s/'.$site->slug.'/account');

        $customer = Customer::query()->withoutGlobalScopes()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame($site->business_id, $customer->business_id);
    }

    public function test_same_email_can_sign_up_on_two_sites_and_logins_stay_separate(): void
    {
        $alpha = $this->publishedSite($this->makeBusiness(['business_name' => 'Alpha'])[0]);
        $bravo = $this->publishedSite($this->makeBusiness(['business_name' => 'Bravo'])[0]);
        $this->makeCustomer($alpha->business, ['email' => 'same@example.com']);
        $this->makeCustomer($bravo->business, ['email' => 'same@example.com', 'password' => 'OtherSecure1!x']);

        $this->post('/s/alpha/login', ['login' => 'same@example.com', 'password' => 'OtherSecure1!x'])
            ->assertSessionHasErrors('login');
        $this->post('/s/alpha/login', ['login' => 'same@example.com', 'password' => self::TEST_PASSWORD])
            ->assertRedirect('/s/alpha/account');

        // Signed in on Alpha is a guest on Bravo.
        $this->get('/s/bravo/account')->assertRedirect('/s/bravo/login');
    }

    public function test_customer_can_change_password(): void
    {
        $site = $this->publishedSite();
        $customer = $this->makeCustomer($site->business);
        $this->actingAs($customer, 'customer');

        $this->put('/s/'.$site->slug.'/account/profile/password', [
            'current_password' => self::TEST_PASSWORD,
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecurePass1!', $customer->fresh()->password));
    }

    public function test_banned_customer_cannot_log_in(): void
    {
        $site = $this->publishedSite();
        $this->makeCustomer($site->business, ['email' => 'banned@example.com', 'status' => CustomerStatus::Banned]);

        $this->post('/s/'.$site->slug.'/login', ['login' => 'banned@example.com', 'password' => self::TEST_PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertGuest('customer');
    }

    public function test_reset_link_points_at_the_customers_own_site(): void
    {
        Notification::fake();
        $site = $this->publishedSite();
        $customer = $this->makeCustomer($site->business, ['email' => 'reset@example.com']);

        $this->post('/s/'.$site->slug.'/forgot-password', ['email' => 'reset@example.com'])
            ->assertSessionHas('success');

        Notification::assertSentTo($customer, ResetPassword::class, fn ($notification) => str_contains(
            $notification->toMail($customer)->actionUrl,
            '/s/'.$site->slug.'/reset-password/',
        ));
    }

    public function test_staff_manage_only_their_own_customers(): void
    {
        [$business, $owner] = $this->makeBusiness();
        [$otherBusiness] = $this->makeBusiness();
        $mine = $this->makeCustomer($business, ['name' => 'Mine Person', 'email' => 'mine@example.com']);
        $theirs = $this->makeCustomer($otherBusiness, ['name' => 'Theirs Person', 'email' => 'theirs@example.com']);

        $this->actingAs($owner, 'staff');

        $this->get(route('frontend.admin.customers.index'))
            ->assertOk()
            ->assertSee('Mine Person')
            ->assertDontSee('Theirs Person');

        $this->put(route('frontend.admin.customers.status', $mine), ['status' => 'banned'])->assertSessionHas('success');
        $this->assertTrue($mine->fresh()->isBanned());

        $this->put(route('frontend.admin.customers.status', $theirs), ['status' => 'banned'])->assertNotFound();
    }

    public function test_customer_and_staff_sessions_are_exclusive(): void
    {
        $site = $this->publishedSite();
        $this->makeStaff(['email' => 'staff-portal@example.com']);
        $this->makeCustomer($site->business, ['email' => 'customer-portal@example.com']);

        $this->post(route('staff.login.store'), ['email' => 'staff-portal@example.com', 'password' => self::TEST_PASSWORD]);
        $this->assertAuthenticated('staff');

        $this->post('/s/'.$site->slug.'/login', ['login' => 'customer-portal@example.com', 'password' => self::TEST_PASSWORD]);

        $this->assertAuthenticated('customer');
        $this->assertGuest('staff');
    }
}
