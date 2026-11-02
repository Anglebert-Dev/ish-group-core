<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Services\Module\ModuleManager;
use App\Support\Locale\Locales;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Billable modules (Super Admin → Modules), the pricing link, and languages.
 */
class ModuleBillableAndLocaleTest extends TestCase
{
    private ?string $tempLang = null;

    protected function tearDown(): void
    {
        if ($this->tempLang) {
            File::deleteDirectory($this->tempLang);
        }

        parent::tearDown();
    }

    public function test_the_superadmin_chooses_which_modules_are_sold(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        $this->withConfirmedPassword();
        $modules = app(ModuleManager::class);

        $this->get(route('superadmin.modules.index'))->assertOk()->assertSee(__('body.module_in_packages'))->assertSee(__('body.module_free_always'));
        $this->assertFalse($modules->isBillingExempt('Savings'));

        $this->post(route('superadmin.modules.billable', 'Savings'), ['billable' => 0])->assertRedirect(route('superadmin.modules.index'));
        $this->assertTrue(app(ModuleManager::class)->isBillingExempt('Savings'));
        $this->assertTrue(ActivityLog::query()->where('description', 'Module Savings is now free for every business')->exists());

        $this->post(route('superadmin.modules.billable', 'Savings'), ['billable' => 1]);
        $this->assertFalse(app(ModuleManager::class)->isBillingExempt('Savings'));

        // Billing itself is always free.
        $this->post(route('superadmin.modules.billable', 'Billing'), ['billable' => 1])->assertSessionHas('error');
        $this->assertTrue(app(ModuleManager::class)->isBillingExempt('Billing'));
    }

    public function test_the_terminal_twin(): void
    {
        $this->artisan('core:module', ['action' => 'free', 'module' => 'Savings'])->assertSuccessful();
        $this->assertTrue(app(ModuleManager::class)->isBillingExempt('Savings'));
        $this->artisan('core:module', ['action' => 'billable', 'module' => 'Savings'])->assertSuccessful();
        $this->assertFalse(app(ModuleManager::class)->isBillingExempt('Savings'));
    }

    public function test_plans_link_only_while_billing_is_on(): void
    {
        $modules = app(ModuleManager::class);
        $modules->install('Billing');
        $this->assertTrue($modules->isActive('Billing'));
        $this->get(route('staff.login'))->assertOk()->assertSee(route('pricing'), false);

        $modules->toggle('Billing', false);
        $this->get(route('staff.login'))->assertOk()->assertDontSee(route('pricing'), false);
    }

    public function test_with_one_language_the_menu_shows_it_and_login_shows_no_switcher(): void
    {
        $this->assertSame(['en' => 'English'], Locales::available());

        $this->get(route('staff.login'))->assertOk()->assertDontSee('id="language-switcher"', false);

        $this->actingAsStaff();
        $this->get(route('dashboard'))->assertOk()->assertSee(__('body.language'))->assertSee('<span lang="en">English</span>', false);
    }

    public function test_users_pick_a_language_and_the_business_sets_the_default(): void
    {
        $this->addFrench();
        $owner = $this->actingAsStaff();

        $this->get(route('staff.login'));
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertRedirect();
        $this->assertSame('fr', $owner->fresh()->locale);
        $this->assertTrue(ActivityLog::query()->where('description', 'Language changed to fr')->exists());
        $this->get(route('dashboard'))->assertOk()->assertSee('<html lang="fr">', false)->assertSee('Langue');

        $this->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');

        // A colleague who never chose follows the business default.
        $this->put(route('settings.update'), ['section' => 'details', 'name' => $owner->business->name, 'locale' => 'fr'])->assertSessionHasNoErrors();
        $colleague = $this->makeMember($owner->business, ['list users.user']);
        $this->flushSession();
        $this->actingAs($colleague, 'staff');
        $this->get(route('dashboard'))->assertOk()->assertSee('<html lang="fr">', false);
    }

    public function test_guests_switch_for_their_session(): void
    {
        $this->addFrench();

        $this->get(route('staff.login'))->assertOk()->assertSee('id="language-switcher"', false);
        $this->post(route('locale.update'), ['locale' => 'fr']);
        $this->get(route('staff.login'))->assertOk()->assertSee('<html lang="fr">', false);
    }

    private function addFrench(): void
    {
        $this->tempLang = resource_path('lang/fr');
        File::ensureDirectoryExists($this->tempLang);
        File::put($this->tempLang.'/body.php', "<?php\n\nreturn ['language' => 'Langue'];\n");
    }
}
