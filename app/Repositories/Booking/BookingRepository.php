<?php

namespace App\Repositories\Booking;

use App\Models\Booking\Booking;
use App\Repositories\BaseRepository;

class BookingRepository extends BaseRepository
{
    public function __construct(Booking $model)
    {
        parent::__construct($model);
    }
}
