<?php

namespace Modules\Distribution\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Only the sha256 of a token is stored; the token itself is shown (or put
 * into a zip) once, when it is made.
 */
class SiteToken extends Model
{
    protected $table = 'distribution_site_tokens';

    protected $fillable = ['site_id', 'token_hash', 'last4', 'build_id', 'last_used_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
