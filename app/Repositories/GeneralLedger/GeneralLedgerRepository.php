<?php

namespace App\Repositories\GeneralLedger;

use App\Models\GeneralLedger\GeneralLedger;
use App\Repositories\BaseRepository;

class GeneralLedgerRepository extends BaseRepository
{
    public function __construct(GeneralLedger $model)
    {
        parent::__construct($model);
    }

    public function findByReference(string $reference): ?GeneralLedger
    {
        return $this->query()->where('reference', $reference)->first();
    }
}
