<?php

namespace App\Services\Extra;

use App\Enums\ExtraStatus;
use App\Models\Extra\Extra;
use App\Libraries\ListExport;
use App\Repositories\Extra\ExtraRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class ExtraService extends BaseService
{
    public function __construct(protected ExtraRepository $extras)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->extras->query()->orderBy('name')->paginate(15);
    }

    public function exportList(): ListExport
    {
        $rows = $this->extras->query()
            ->orderBy('name')
            ->get()
            ->map(function (Extra $extra) {
                $price = number_format((float) $extra->price, 0).' RWF';

                if ($extra->isChauffeur()) {
                    $price .= ' '.__('body.price_per_day_short');
                } elseif ($extra->isDelivery()) {
                    $price .= ' '.__('body.price_per_delivery_short');
                } elseif ($extra->isCollection()) {
                    $price .= ' '.__('body.price_per_collection_short');
                } elseif ($extra->isExtension()) {
                    $price .= ' '.__('body.price_per_extension_day_short');
                }

                return [
                    $extra->name,
                    $extra->type->label(),
                    $price,
                    ucfirst($extra->status->value),
                ];
            })
            ->all();

        return ListExport::make(__('body.extras'), 'extras', [
            __('body.extra_name'),
            __('body.extra_type'),
            __('body.price'),
            __('body.status'),
        ], $rows);
    }

    public function optionalCatalog(): Collection
    {
        if (! Schema::hasTable('extras')) {
            return new Collection;
        }

        if (! Schema::hasColumn('extras', 'type')) {
            return $this->extras->query()
                ->where('status', ExtraStatus::Active->value)
                ->orderBy('name')
                ->get();
        }

        return $this->extras->activeOptional();
    }

    public function chauffeurExtra(): ?Extra
    {
        if (! Schema::hasTable('extras') || ! Schema::hasColumn('extras', 'type')) {
            return null;
        }

        return $this->extras->activeChauffeur();
    }

    public function chauffeurDailyFee(): string
    {
        return (string) ($this->chauffeurExtra()?->price ?? '0.00');
    }

    public function deliveryExtra(): ?Extra
    {
        if (! Schema::hasTable('extras') || ! Schema::hasColumn('extras', 'type')) {
            return null;
        }

        return $this->extras->activeDelivery();
    }

    public function deliveryFee(): string
    {
        return (string) ($this->deliveryExtra()?->price ?? '0.00');
    }

    public function collectionExtra(): ?Extra
    {
        if (! Schema::hasTable('extras') || ! Schema::hasColumn('extras', 'type')) {
            return null;
        }

        return $this->extras->activeCollection();
    }

    public function collectionFee(): string
    {
        return (string) ($this->collectionExtra()?->price ?? '0.00');
    }

    public function extensionExtra(): ?Extra
    {
        if (! Schema::hasTable('extras') || ! Schema::hasColumn('extras', 'type')) {
            return null;
        }

        return $this->extras->activeExtension();
    }

    public function extensionDailySurcharge(): string
    {
        return (string) ($this->extensionExtra()?->price ?? '0.00');
    }

    public function create(array $data): array
    {
        $extra = new Extra($data);

        $this->extras->save($extra);

        return $this->success($extra, __('body.extra_created'));
    }

    public function update(Extra $extra, array $data): array
    {
        $extra->fill($data);

        $this->extras->save($extra);

        return $this->success($extra, __('body.extra_updated'));
    }


    public function updateStatus(Extra $extra, ExtraStatus $status): array
    {
        $extra->status = $status;

        $this->extras->save($extra);

        return $this->success($extra, __('body.extra_status_updated'));
    }

    public function delete(Extra $extra): array
    {
        $extra->delete();

        return $this->success(null, __('body.extra_deleted'));
    }
}
