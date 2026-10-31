<?php

namespace Modules\Distribution\Tests\Feature;

use App\Services\Module\ModuleManager;
use Illuminate\Support\Facades\Storage;
use Modules\Distribution\Enums\BuildStatus;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\CheckIn;
use Modules\Distribution\Services\SiteService;
use Tests\TestCase;

class PruneTest extends TestCase
{
    public function test_prune_deletes_expired_zips_and_old_check_ins(): void
    {
        app(ModuleManager::class)->install('Distribution');
        Storage::fake('local');
        $site = app(SiteService::class)->create(['name' => 'Site'], ['Savings'])['data'];

        Storage::disk('local')->put('distribution/builds/old.zip', 'x');
        Storage::disk('local')->put('distribution/builds/new.zip', 'x');
        $old = Build::query()->create(['site_id' => $site->id, 'modules' => [], 'status' => BuildStatus::Ready, 'path' => 'distribution/builds/old.zip', 'expires_at' => now()->subDay()]);
        $new = Build::query()->create(['site_id' => $site->id, 'modules' => [], 'status' => BuildStatus::Ready, 'path' => 'distribution/builds/new.zip', 'expires_at' => now()->addDay()]);

        CheckIn::query()->create(['site_id' => $site->id])->forceFill(['created_at' => now()->subDays(91)])->save();
        CheckIn::query()->create(['site_id' => $site->id]);

        $this->artisan('distribution:prune')->assertSuccessful();

        $this->assertSame(BuildStatus::Expired, $old->fresh()->status);
        Storage::disk('local')->assertMissing('distribution/builds/old.zip');
        $this->assertSame(BuildStatus::Ready, $new->fresh()->status);
        Storage::disk('local')->assertExists('distribution/builds/new.zip');
        $this->assertSame(1, CheckIn::query()->count());
    }
}
