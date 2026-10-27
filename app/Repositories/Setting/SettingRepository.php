<?php

namespace App\Repositories\Setting;

use App\Models\Setting\Setting;
use App\Repositories\BaseRepository;

class SettingRepository extends BaseRepository
{
    public function __construct(Setting $model)
    {
        parent::__construct($model);
    }

    public function first(): ?Setting
    {
        return $this->query()->first();
    }
}
