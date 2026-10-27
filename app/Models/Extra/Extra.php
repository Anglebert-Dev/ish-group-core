<?php

namespace App\Models\Extra;

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Models\BaseModel;

class Extra extends BaseModel
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'type' => ExtraType::class,
            'status' => ExtraStatus::class,
        ];
    }

    public function isChauffeur(): bool
    {
        return $this->type === ExtraType::Chauffeur;
    }

    public function isDelivery(): bool
    {
        return $this->type === ExtraType::Delivery;
    }

    public function isCollection(): bool
    {
        return $this->type === ExtraType::Collection;
    }

    public function isExtension(): bool
    {
        return $this->type === ExtraType::Extension;
    }
}
