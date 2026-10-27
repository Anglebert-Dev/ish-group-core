<?php

namespace App\Services\Permission;

use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class PermissionService extends BaseService
{
    public function __construct(protected AccessCatalogue $catalogue)
    {
    }

    /**
     * Make sure every permission in the catalogue (core + active modules)
     * exists. Cheap to call often: it only writes when the catalogue changed.
     */
    public function initPermissions(bool $force = false): void
    {
        $names = $this->catalogue->permissionNames();
        $cacheKey = 'permissions_seeded_'.md5(serialize($names));

        if (! $force && Cache::has($cacheKey)) {
            return;
        }

        Cache::lock('permission_init_lock', 30)->block(10, function () use ($names, $cacheKey) {
            foreach ($names as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'staff']);
            }

            Cache::forever($cacheKey, true);
        });
    }
}
