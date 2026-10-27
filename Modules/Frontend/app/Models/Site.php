<?php

namespace Modules\Frontend\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

/**
 * A business's public website. Content is stored sparsely: content() merges
 * what the business saved over config('frontend.defaults').
 */
class Site extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'frontend_sites';

    protected $fillable = [
        'business_id',
        'slug',
        'is_published',
        'content',
    ];

    private ?array $resolved = null;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'content' => 'array',
        ];
    }

    /**
     * Full content (defaults + saved), with ":company" filled in.
     */
    public function content(): array
    {
        return $this->resolved ??= $this->fillCompany(
            self::merge(config('frontend.defaults', []), $this->content ?? [])
        );
    }

    /**
     * Dot-path access, e.g. $site->get('home.hero.title').
     */
    public function get(string $path, mixed $default = null): mixed
    {
        return data_get($this->content(), $path, $default);
    }

    /**
     * Raw (un-substituted) value for edit forms, so ":company" stays a
     * placeholder instead of being saved as today's name.
     */
    public function raw(string $path, mixed $default = null): mixed
    {
        return data_get(self::merge(config('frontend.defaults', []), $this->content ?? []), $path, $default);
    }

    public function refreshContent(): void
    {
        $this->resolved = null;
    }

    /**
     * Associative arrays merge recursively; lists are replaced whole.
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && ! array_is_list($value) && ! array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function fillCompany(array $content): array
    {
        $name = $this->business?->companyName() ?? '';

        array_walk_recursive($content, function (&$value) use ($name) {
            if (is_string($value)) {
                $value = str_replace(':company', $name, $value);
            }
        });

        return $content;
    }
}
