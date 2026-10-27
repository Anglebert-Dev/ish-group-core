<?php

namespace App\Services\Setting;

use App\Libraries\UploadLibrary;
use App\Models\Setting\Setting;
use App\Repositories\Setting\SettingRepository;
use App\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class SettingService extends BaseService
{
    private ?Setting $current = null;

    public function __construct(protected SettingRepository $settings)
    {
    }

    public function current(): Setting
    {
        if ($this->current) {
            return $this->current;
        }

        if (! Schema::hasTable('settings')) {
            return $this->current = $this->fallback();
        }

        $setting = $this->settings->first();

        if (! $setting) {
            $setting = new Setting([
                'company_name' => Setting::DEFAULT_COMPANY_NAME,
                'brand_color' => Setting::DEFAULT_BRAND_COLOR,
                'status' => 'active',
            ]);

            $this->settings->save($setting);
        }

        return $this->current = $setting;
    }

    public function update(Setting $setting, array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): array
    {
        $setting->fill($data);

        if ($logo) {
            if ($setting->logo_path) {
                UploadLibrary::delete($setting->logo_path, 'public');
            }
            $setting->logo_path = UploadLibrary::store($logo, 'branding', 'public');
        }

        if ($favicon) {
            if ($setting->favicon_path) {
                UploadLibrary::delete($setting->favicon_path, 'public');
            }
            $setting->favicon_path = UploadLibrary::store($favicon, 'branding', 'public');
        }

        $this->settings->save($setting);

        $this->current = $setting;

        return $this->success($setting, __('body.settings_updated'));
    }

    private function fallback(): Setting
    {
        return new Setting([
            'company_name' => Setting::DEFAULT_COMPANY_NAME,
            'brand_color' => Setting::DEFAULT_BRAND_COLOR,
            'status' => 'active',
        ]);
    }
}
