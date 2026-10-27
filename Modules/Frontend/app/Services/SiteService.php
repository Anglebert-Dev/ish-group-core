<?php

namespace Modules\Frontend\Services;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Services\BaseService;
use Illuminate\Support\Str;
use Modules\Frontend\Models\Site;

class SiteService extends BaseService
{
    /** Slugs that would clash with paths or read badly. */
    public const RESERVED_SLUGS = ['app', 'admin', 'api', 's', 'www', 'login', 'register'];

    /**
     * The business's site, created (unpublished) on first use.
     */
    public function forBusiness(Business $business): Site
    {
        $site = Site::query()->withoutGlobalScope('business')->where('business_id', $business->id)->first();

        if (! $site) {
            $site = new Site([
                'slug' => $this->uniqueSlug($business->name),
                'is_published' => false,
            ]);
            $site->business_id = $business->id;
            $site->save();
        }

        return $site->setRelation('business', $business);
    }

    public function findBySlug(string $slug): ?Site
    {
        return Site::query()->withoutGlobalScope('business')
            ->with('business')
            ->where('slug', strtolower($slug))
            ->first();
    }

    /**
     * The site "/" shows: the primary business's, if published.
     */
    public function primary(): ?Site
    {
        $id = System::getProperty('primary_business_id');
        $business = $id ? Business::query()->find($id) : Business::query()->orderBy('id')->first();

        if (! $business) {
            return null;
        }

        $site = Site::query()->withoutGlobalScope('business')->with('business')->where('business_id', $business->id)->first();

        return $site?->is_published ? $site : null;
    }

    /**
     * Save one tab of the editor. Only content keys go into the JSON; the
     * site columns (slug, published) are saved directly.
     */
    public function update(Site $site, string $tab, array $data): array
    {
        if ($tab === 'general') {
            $site->slug = strtolower($data['slug']);
            $site->is_published = (bool) ($data['is_published'] ?? false);
            unset($data['slug'], $data['is_published']);
        }

        $site->content = Site::merge($site->content ?? [], $this->normalise($data));
        $site->save();
        $site->refreshContent();

        return $this->success($site, __('body.website_saved'));
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'site';

        if (in_array($base, self::RESERVED_SLUGS, true)) {
            $base .= '-site';
        }

        $slug = $base;
        $n = 2;

        while (Site::query()->withoutGlobalScope('business')
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * Checkbox values to real booleans; repeater rows re-indexed and blank
     * rows dropped.
     */
    private function normalise(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = array_is_list($value) && $value !== [] && is_array($value[0] ?? null)
                    ? array_values(array_filter(array_map(fn ($row) => $this->normalise((array) $row), $value), fn ($row) => array_filter($row, fn ($v) => filled($v)) !== []))
                    : $this->normalise($value);
            } elseif (in_array($value, ['0', '1'], true) && (str_starts_with((string) $key, 'show') || str_starts_with((string) $key, 'allow'))) {
                $data[$key] = $value === '1';
            } elseif ($value === null) {
                $data[$key] = '';
            }
        }

        return $data;
    }
}
