<?php

namespace Modules\Distribution\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\User;
use App\Services\Module\ModuleManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\BuildService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;
use ZipArchive;

/**
 * What the customer tells us about their server: typed in or CSV, used by
 * the setup guide and the build. docs/self-hosting-build-guide.md §6.
 */
class ServerDetailsTest extends TestCase
{
    protected User $root;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Distribution');
        $this->root = $this->makeSuperAdmin(['email' => 'root@example.com']);
        $keys = SigningKeys::generate();
        config(['distribution.signing_secret' => $keys['secret'], 'distribution.public_keys' => []]);
        Storage::fake('local');

        $this->site = app(SiteService::class)->create(['name' => 'Acme Finance'], ['Savings'])['data'];
        $this->actingAs($this->root, 'staff');
    }

    public function test_typed_in_details_are_cleaned_and_saved(): void
    {
        $this->get(route('superadmin.distribution.sites.server.edit', $this->site))->assertOk()->assertSee(__('distribution::distribution.server_php_path'));

        $this->put(route('superadmin.distribution.sites.server.update', $this->site), [
            'hosting_type' => 'Shared', 'php_version' => '8.3', 'php_path' => '/usr/local/bin/php', 'cron_only' => '1',
            'db_driver' => 'PostgreSQL', 'db_port' => '5432', 'app_url' => 'https://finance.acme.com', 'admin_email' => 'jane@acme.com',
            'db_password' => 'never-stored',
        ])->assertRedirect(route('superadmin.distribution.sites.show', $this->site));

        $details = $this->site->fresh()->server_details;
        $this->assertSame('shared', $details['hosting_type']);
        $this->assertSame('pgsql', $details['db_driver']);
        $this->assertSame(5432, $details['db_port']);
        $this->assertTrue($details['cron_only']);
        $this->assertArrayNotHasKey('db_password', $details);
        $this->assertSame('https://finance.acme.com', $this->site->fresh()->app_url);
        $this->assertTrue(ActivityLog::query()->where('action', 'distribution_server_details_saved')->exists());
    }

    public function test_email_without_encryption_is_a_valid_answer(): void
    {
        $this->put(route('superadmin.distribution.sites.server.update', $this->site), ['mail_host' => 'mailpit', 'mail_port' => '1025', 'mail_encryption' => 'none'])
            ->assertRedirect();

        $this->assertSame('none', $this->site->fresh()->server_details['mail_encryption']);
        $this->assertArrayNotHasKey('scheme', \Modules\Distribution\Support\ServerDetails::installerDefaults($this->site->fresh()->server_details, 'x')['mail']);
    }

    public function test_unsafe_or_wrong_answers_are_refused(): void
    {
        $this->put(route('superadmin.distribution.sites.server.update', $this->site), ['php_path' => '/usr/bin/php; rm -rf /', 'db_driver' => 'oracle'])
            ->assertSessionHasErrors();

        $this->assertNull($this->site->fresh()->server_details);
    }

    public function test_the_csv_form_round_trips(): void
    {
        $template = $this->get(route('superadmin.distribution.sites.server.template', $this->site))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();

        $this->assertStringContainsString('field,value,"what to write",example', $template);
        $this->assertStringContainsString('php_path', $template);
        $this->assertStringNotContainsStringIgnoringCase('password,', $template);

        // The customer fills in the "value" column (as Excel would save it).
        $filled = "\xEF\xBB\xBFfield,value,what to write,example\n"
            ."php_version,8.3.12,,\nphp_path,/opt/cpanel/ea-php83/root/usr/bin/php,,\ninstall_path,/home/acme/finance,,\n"
            ."web_root_is_public,no,,\ncron_only,yes,,\ndb_driver,mysql,,\ndb_database,acme_fin,,\ndb_username,acme_user,,\n"
            ."admin_email,jane@acme.com,,\ndb_password,secret,,\n";

        $this->post(route('superadmin.distribution.sites.server.upload', $this->site), ['file' => UploadedFile::fake()->createWithContent('server.csv', $filled)])
            ->assertRedirect(route('superadmin.distribution.sites.show', $this->site))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'db_password'));

        $details = $this->site->fresh()->server_details;
        $this->assertSame('/opt/cpanel/ea-php83/root/usr/bin/php', $details['php_path']);
        $this->assertFalse($details['web_root_is_public']);
        $this->assertArrayNotHasKey('db_password', $details);
    }

    public function test_a_csv_with_mistakes_saves_nothing(): void
    {
        $this->post(route('superadmin.distribution.sites.server.upload', $this->site), [
            'file' => UploadedFile::fake()->createWithContent('server.csv', "field,value\napp_url,not a url\n"),
        ])->assertSessionHasErrors();

        $this->post(route('superadmin.distribution.sites.server.upload', $this->site), [
            'file' => UploadedFile::fake()->createWithContent('server.csv', "field,value\nphp_path,\n"),
        ])->assertSessionHasErrors();

        $this->assertNull($this->site->fresh()->server_details);
    }

    public function test_the_guide_uses_their_answers(): void
    {
        app(SiteService::class)->saveServerDetails($this->site, ['php_path' => '/usr/local/bin/php', 'install_path' => '/home/acme/finance', 'cron_only' => true], 'form');

        $this->get(route('superadmin.distribution.sites.guide', $this->site))->assertOk()
            ->assertSee('* * * * * cd /home/acme/finance &amp;&amp; /usr/local/bin/php artisan schedule:run', false)
            ->assertSee(__('distribution::distribution.guide_after_queue_cron'))
            ->assertSee('/home/acme/finance/public');

        $this->get(route('superadmin.distribution.sites.show', $this->site))->assertOk()->assertSee('/usr/local/bin/php');
    }

    public function test_a_server_with_old_php_is_not_built_for(): void
    {
        app(SiteService::class)->saveServerDetails($this->site, ['php_version' => '8.1'], 'form');

        $result = app(BuildService::class)->start($this->site);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('PHP 8.1 is too old', $result['message']);
        $this->get(route('superadmin.distribution.sites.guide', $this->site))->assertSee('PHP 8.1 is too old');
    }

    public function test_the_zip_carries_their_answers_for_the_installer(): void
    {
        app(SiteService::class)->saveServerDetails($this->site, [
            'db_driver' => 'mysql', 'db_host' => 'db.acme', 'db_database' => 'acme_fin', 'db_username' => 'acme_user',
            'mail_host' => 'mail.acme.com', 'mail_encryption' => 'ssl', 'admin_email' => 'jane@acme.com', 'php_path' => '/usr/local/bin/php', 'cron_only' => true,
        ], 'form');

        $build = app(BuildService::class)->run(app(BuildService::class)->start($this->site)['data']['build'], vendor: false)['data'];
        $zip = new ZipArchive;
        $zip->open(app(BuildService::class)->absolutePath($build));
        $manifest = json_decode($zip->getFromName('acme-finance/storage/app/distribution/site.json'), true);
        $zip->close();

        $this->assertSame(['driver' => 'mysql', 'host' => 'db.acme', 'database' => 'acme_fin', 'username' => 'acme_user'], $manifest['defaults']['database']);
        $this->assertSame('smtps', $manifest['defaults']['mail']['scheme']);
        $this->assertSame('jane@acme.com', $manifest['defaults']['admin']['email']);
        $this->assertTrue($manifest['defaults']['app']['queue_via_scheduler']);
        $this->assertSame('/usr/local/bin/php', $manifest['defaults']['server']['php_path']);
        $this->assertStringNotContainsStringIgnoringCase('password', json_encode($manifest['defaults']));
    }
}
