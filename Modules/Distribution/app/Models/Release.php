<?php

namespace Modules\Distribution\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * One signed, published version of a module.
 */
class Release extends BaseModel
{
    /** Releases of the core itself use this reserved name. */
    public const CORE = 'Core';

    protected $table = 'distribution_releases';

    protected $fillable = [
        'module', 'version', 'sha256', 'signature', 'size', 'path', 'requires',
        'min_core_version', 'min_php', 'changelog', 'published_at', 'test_only', 'yanked_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'requires' => 'array',
            'published_at' => 'datetime',
            'test_only' => 'boolean',
            'yanked_at' => 'datetime',
        ];
    }

    /** Published and not withdrawn: what sites may be offered. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereNull('yanked_at');
    }

    /**
     * A comparable version from what a site reports ("1.2.0+abc123" → "1.2.0");
     * anything else (an old zip's git commit, "dev") counts as 0.0.0.
     */
    public static function plainVersion(?string $version): string
    {
        return preg_match('/^(\d+\.\d+(\.\d+)?)/', (string) $version, $match) ? $match[1] : '0.0.0';
    }

    /**
     * The newest available release of each of $modules (version order, not date).
     * Releases still "for test sites first" count only when $testSite.
     *
     * @param  list<string>  $modules
     * @return array<string, self> module => release
     */
    public static function latestFor(array $modules, bool $testSite = true): array
    {
        return static::query()->available()->whereIn('module', $modules)
            ->when(! $testSite, fn (Builder $query) => $query->where('test_only', false))
            ->get()
            ->groupBy('module')
            ->map(fn ($releases) => $releases->sort(fn (self $a, self $b) => version_compare($b->version, $a->version))->first())
            ->all();
    }
}
