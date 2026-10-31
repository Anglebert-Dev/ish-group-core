<?php

namespace App\Services\Business;

use App\Models\System\System;
use App\Services\BaseService;

/**
 * Creates the first business and its owner as platform superadmin. Shared
 * by core:install, core:setup and the web installer so every install ends
 * up the same.
 */
class PlatformSetupService extends BaseService
{
    public function __construct(protected BusinessSetupService $businesses)
    {
    }

    /**
     * @param  array{business: string, name: string, email: string, password: string, timezone?: string}  $data
     * @return array{status: bool, data: array{business: \App\Models\Business\Business, owner: \App\Models\User}|null, message: string}
     */
    public function createFirstPlatform(array $data): array
    {
        $result = $this->businesses->createWithOwner(
            array_filter(['name' => $data['business'], 'timezone' => $data['timezone'] ?? null]),
            ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_superadmin' => true],
        );

        if ($result['status']) {
            System::addProperty('primary_business_id', $result['data']['business']->id);
        }

        return $result;
    }
}
