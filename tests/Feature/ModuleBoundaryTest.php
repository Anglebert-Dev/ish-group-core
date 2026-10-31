<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Billing and Distribution never ship to self-hosted sites, so nothing in
 * the core may depend on them (docs/self-hosting-build-guide.md §6).
 */
class ModuleBoundaryTest extends TestCase
{
    public function test_core_never_refers_to_modules_that_do_not_ship(): void
    {
        $offenders = [];

        // Generated, or stripped from every zip by the build (phase 3).
        $ignored = ['bootstrap/cache/', 'database/seeders/DemoFactorySeeder.php'];

        foreach (['app', 'bootstrap', 'config', 'routes', 'resources', 'database'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                $path = $dir.'/'.$file->getRelativePathname();

                if (array_filter($ignored, fn (string $prefix) => str_starts_with($path, $prefix))) {
                    continue;
                }

                if (preg_match('/Modules\\\\+(Billing|Distribution)\\\\|[\'"](billing|distribution)::/', $file->getContents())) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
