<?php

namespace App\Models;

use App\Models\Concerns\HasUuidAudit;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasUuidAudit;
}
