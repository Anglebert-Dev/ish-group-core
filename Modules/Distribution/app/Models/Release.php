<?php

namespace Modules\Distribution\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * One signed, published version of a module.
 */
class Release extends BaseModel
{
    protected $table = 'distribution_releases';

    protected $fillable = [
        'module', 'version', 'sha256', 'signature', 'size', 'path', 'requires',
        'min_core_version', 'changelog', 'published_at', 'test_only', 'yanked_at',
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
