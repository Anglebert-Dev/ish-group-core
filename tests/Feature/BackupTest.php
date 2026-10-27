<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Models\System\System;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    public function test_backup_zips_database_and_uploads_and_prunes(): void
    {
        $dir = storage_path('framework/testing/backup-'.uniqid());
        @mkdir($dir, 0700, true);
        file_put_contents($dir.'/db.sqlite', 'fake-db');
        config(['database.connections.sqlite.database' => $dir.'/db.sqlite']);
        Storage::fake('local');
        System::addProperty('backup_keep', '1');

        $service = app(BackupService::class);
        $first = $service->create();
        $this->assertTrue($first['status'], $first['message']);

        $zip = new ZipArchive;
        $zip->open($service->path($first['data']));
        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $this->assertNotFalse($zip->locateName('backup.json'));
        $zip->close();

        $this->travel(2)->seconds();
        $second = $service->create();
        $this->assertCount(1, $service->list(), 'kept only the newest');
        $this->assertSame($second['data'], $service->list()->first()['name']);

        @unlink($dir.'/db.sqlite');
        @rmdir($dir);
    }

    public function test_superadmin_backup_screens(): void
    {
        Bus::fake();
        Storage::fake('local');
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.backups.index'))->assertOk();
        $this->post(route('superadmin.backups.store'))->assertSessionHas('success');
        Bus::assertDispatched(CreateBackup::class);

        $this->put(route('superadmin.backups.settings'), ['backup_daily' => '1', 'backup_keep' => 14])->assertSessionHas('success');
        $this->assertSame('1', System::getProperty('backup_daily'));

        Storage::disk('local')->put('backups/backup-test.zip', 'zip');
        $this->withConfirmedPassword()->get(route('superadmin.backups.download', 'backup-test.zip'))->assertOk();
        $this->withConfirmedPassword()->get(route('superadmin.backups.download', '..env'))->assertNotFound();
        $this->withConfirmedPassword()->delete(route('superadmin.backups.destroy', 'backup-test.zip'))->assertSessionHas('success');
        Storage::disk('local')->assertMissing('backups/backup-test.zip');
    }

    public function test_backups_are_superadmin_only(): void
    {
        $this->actingAsStaff();

        $this->get(route('superadmin.backups.index'))->assertForbidden();
    }
}
