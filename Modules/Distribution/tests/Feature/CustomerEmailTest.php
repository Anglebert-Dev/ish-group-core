<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\User;
use App\Services\Module\ModuleManager;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Distribution\Enums\BuildStatus;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Notifications\CustomerEmail;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;

/**
 * The emails to a self-hosting customer and the signed links in them.
 */
class CustomerEmailTest extends TestCase
{
    protected User $root;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->root = $this->makeSuperAdmin(['email' => 'root@example.com']);
        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret']]);
        Storage::fake('local');
        Notification::fake();

        $this->site = app(SiteService::class)->create(['name' => 'Acme Finance', 'customer_name' => 'Jane', 'contact_email' => 'jane@acme.test'], ['Savings'])['data'];
        app(SiteService::class)->saveServerDetails($this->site, ['install_path' => '/home/acme/finance', 'php_path' => '/usr/local/bin/php', 'app_url' => 'https://finance.acme.test'], 'form');
        $this->actingAs($this->root, 'staff');
    }

    public function test_the_preparation_email_carries_the_guide_and_the_form(): void
    {
        $this->post(route('superadmin.distribution.sites.email.preparation', $this->site), ['email' => 'it@acme.test'])->assertSessionHas('success');

        Notification::assertSentOnDemand(CustomerEmail::class, function (CustomerEmail $mail, array $channels, AnonymousNotifiable $to) {
            return $to->routes['mail'] === 'it@acme.test'
                && str_contains($mail->subject, 'Acme Finance')
                && str_contains((string) $mail->actionUrl, '/self-hosting/guide/')
                && str_contains((string) $mail->actionUrl, 'signature=')
                && str_contains(array_values($mail->attachments)[0], 'php_path,/usr/local/bin/php')
                && array_key_first($mail->attachments) === 'server-details-acme-finance.csv';
        });
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_preparation_emailed')->exists());
    }

    public function test_the_package_email_has_the_link_code_and_steps(): void
    {
        $build = $this->readyBuild();

        $this->post(route('superadmin.distribution.sites.builds.email', [$this->site, $build]), ['email' => 'jane@acme.test'])->assertSessionHas('success');

        Notification::assertSentOnDemand(CustomerEmail::class, function (CustomerEmail $mail) use ($build) {
            $text = implode("\n", $mail->lines);

            return str_contains($text, $build->install_code)
                && str_contains($text, '/home/acme/finance')
                && str_contains($text, 'https://finance.acme.test')
                && str_contains($text, 'cd /home/acme/finance && /usr/local/bin/php artisan schedule:run')
                && str_contains((string) $mail->actionUrl, '/self-hosting/download/');
        });

        $build->refresh();
        $this->assertSame('jane@acme.test', $build->emailed_to);
        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertSee($build->install_code)->assertSee('Emailed to jane@acme.test');
    }

    public function test_signed_links_work_without_an_account_and_only_when_signed(): void
    {
        $build = $this->readyBuild();
        auth('staff')->logout();

        $this->get(URL::temporarySignedRoute('distribution.customer.download', $build->expires_at, ['build' => $build]))->assertOk()->assertDownload('acme.zip');
        $this->get(URL::temporarySignedRoute('distribution.customer.guide', now()->addDay(), ['site' => $this->site]))->assertOk()->assertSee('/home/acme/finance');

        $this->get(route('distribution.customer.download', $build))->assertForbidden();
        $this->get(URL::temporarySignedRoute('distribution.customer.download', $build->expires_at, ['build' => $build]).'x')->assertForbidden();
        $this->get(URL::temporarySignedRoute('distribution.customer.guide', now()->subMinute(), ['site' => $this->site]))->assertForbidden();

        $build->update(['expires_at' => now()->subMinute()]);
        $this->get(URL::temporarySignedRoute('distribution.customer.download', now()->addDay(), ['build' => $build]))->assertNotFound();
    }

    public function test_an_expired_zip_cannot_be_emailed(): void
    {
        $build = $this->readyBuild();
        $build->update(['expires_at' => now()->subDay()]);

        $this->post(route('superadmin.distribution.sites.builds.email', [$this->site, $build]), ['email' => 'jane@acme.test'])->assertSessionHas('error');
        Notification::assertNothingSent();
    }

    public function test_new_builds_keep_their_code_encrypted(): void
    {
        $started = app(BuildService::class)->start($this->site);

        $build = $started['data']['build']->fresh();
        $this->assertSame($started['data']['code'], $build->install_code);
        $this->assertNotSame($started['data']['code'], \Illuminate\Support\Facades\DB::table('distribution_builds')->value('install_code'), 'stored encrypted');
    }

    private function readyBuild(): Build
    {
        Storage::disk('local')->put('distribution/builds/acme.zip', 'zip');

        return Build::query()->create([
            'site_id' => $this->site->id, 'modules' => ['Savings'], 'status' => BuildStatus::Ready, 'install_code' => 'ABCDE-FGH23',
            'path' => 'distribution/builds/acme.zip', 'filename' => 'acme.zip', 'expires_at' => now()->addDays(14),
        ]);
    }
}
