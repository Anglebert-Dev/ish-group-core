<?php

namespace App\Services\Setting;

use App\Libraries\UploadLibrary;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Repositories\Business\BusinessRepository;
use App\Services\BaseService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * Business settings & branding. The branding shown is the signed-in staff
 * user's business; on pages without one (login, public site) it is the
 * platform's primary business.
 */
class SettingService extends BaseService
{
    public function __construct(
        protected BusinessRepository $businesses,
        protected CurrentBusiness $current,
    ) {
    }

    public function current(): Business
    {
        if (! Schema::hasTable('businesses')) {
            return $this->fallback();
        }

        return $this->current->get() ?? $this->primary() ?? $this->fallback();
    }

    /**
     * The business the public site and guest pages represent:
     * system "primary_business_id", else the first business created.
     */
    public function primary(): ?Business
    {
        $id = System::getProperty('primary_business_id');

        return ($id ? $this->businesses->find($id) : null) ?? $this->businesses->first();
    }

    public function update(Business $business, array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): array
    {
        $business->fill($data);

        if ($logo) {
            if ($business->logo_path) {
                UploadLibrary::delete($business->logo_path, 'public');
            }
            $business->logo_path = UploadLibrary::store($logo, 'branding', 'public');
        }

        if ($favicon) {
            if ($business->favicon_path) {
                UploadLibrary::delete($business->favicon_path, 'public');
            }
            $business->favicon_path = UploadLibrary::store($favicon, 'branding', 'public');
        }

        $this->businesses->save($business);

        return $this->success($business, __('body.settings_updated'));
    }

    private function fallback(): Business
    {
        return new Business([
            'name' => Business::DEFAULT_NAME,
            'brand_color' => Business::DEFAULT_BRAND_COLOR,
        ]);
    }
}
