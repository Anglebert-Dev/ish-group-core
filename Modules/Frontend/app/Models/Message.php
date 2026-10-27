<?php

namespace Modules\Frontend\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Message extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'frontend_messages';

    protected $fillable = ['business_id', 'name', 'email', 'phone', 'subject', 'message', 'ip_address', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function name(): string
    {
        return $this->subject ?: $this->name;
    }
}
