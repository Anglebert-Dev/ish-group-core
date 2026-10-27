<?php

namespace App\Repositories\Extra;

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Models\Extra\Extra;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ExtraRepository extends BaseRepository
{
    public function __construct(Extra $model)
    {
        parent::__construct($model);
    }

    public function activeOptional(): Collection
    {
        return $this->query()
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Optional->value)
            ->orderBy('name')
            ->get();
    }

    public function activeChauffeur(): ?Extra
    {
        return $this->query()
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Chauffeur->value)
            ->orderBy('id')
            ->first();
    }

    public function activeDelivery(): ?Extra
    {
        return $this->query()
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Delivery->value)
            ->orderBy('id')
            ->first();
    }

    public function activeCollection(): ?Extra
    {
        return $this->query()
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Collection->value)
            ->orderBy('id')
            ->first();
    }

    public function activeExtension(): ?Extra
    {
        return $this->query()
            ->where('status', ExtraStatus::Active->value)
            ->where('type', ExtraType::Extension->value)
            ->orderBy('id')
            ->first();
    }
}
