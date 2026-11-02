<?php

namespace App\Services\Branding;

use App\Models\Branding\BrandingVersion;
use App\Models\Business\Business;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Setting\SettingService;
use App\Support\Branding\ThemeContrast;
use App\Support\Branding\ThemeLayout;
use App\Support\Branding\ThemeSchema;
use App\Support\Branding\ThemeTokens;
use App\Support\Settings\BusinessSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A business's appearance as versions (docs/branding.md §2.2): edits go to
 * a draft, Publish makes it live. What a page shows is, in order:
 * built-in defaults → the platform's published theme → the business's
 * published theme → its draft (only while previewing).
 */
class ThemeService extends BaseService
{
    /** Session flag: this user is previewing their business's draft. */
    public const PREVIEW = 'branding.preview';

    /**
     * Unsaved choices from the studio, for this request only (business id =>
     * sections). Set by the ThemeStudio middleware for people who may edit.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $studio = [];

    public function __construct(protected SettingService $settings) {}

    /** @param  array<string, mixed>  $sections  each one replaces the draft's */
    public function useStudio(int $businessId, array $sections): void
    {
        $this->studio[$businessId] = $sections;
    }

    public function clearStudio(): void
    {
        $this->studio = [];
    }

    public function inStudio(Business $business): bool
    {
        return isset($this->studio[$business->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function effective(Business $business, bool $withDraft = false): array
    {
        $platform = $this->settings->primary();
        $layers = [];

        if ($platform && $platform->id !== $business->id && $platform->exists) {
            // Only the look carries over: a business's dashboard, menu and notices are its own.
            $layers[] = array_intersect_key($this->publishedPayload($platform), array_flip(ThemeSchema::SHARED));
        }
        if ($business->exists) {
            // A draft is a full copy (it starts from what is live), so it replaces
            // the published version rather than merging into it: lists then
            // shrink as they should, and "reset" really previews the platform look.
            $draft = $withDraft ? $this->draft($business) : null;
            $layers[] = $draft ? ($draft->payload ?? []) : $this->publishedPayload($business);
        }

        $theme = ThemeSchema::clean(array_replace_recursive([], ...$layers ?: [[]]));

        // The studio's form, as typed: whole sections, so a cleared field means "default".
        if ($withDraft && $business->exists && isset($this->studio[$business->id])) {
            $theme = ThemeSchema::clean(array_replace($theme, ThemeSchema::clean($this->studio[$business->id])));
        }

        return $theme;
    }

    /**
     * @return array<string, string>
     */
    public function tokens(Business $business, bool $withDraft = false, bool $dark = false): array
    {
        $theme = $this->effective($business, $withDraft);

        return ThemeTokens::resolve($business->brandColor(), $theme[$dark ? 'colors_dark' : 'colors'] ?? [], $dark);
    }

    public function layout(Business $business, bool $withDraft = false): ThemeLayout
    {
        return new ThemeLayout($this->effective($business, $withDraft));
    }

    /** The business's mode: light, dark or system. */
    public function mode(Business $business, bool $withDraft = false): string
    {
        return $this->effective($business, $withDraft)['mode'] ?? ThemeSchema::DEFAULT_MODE;
    }

    /** May each person choose light/dark for themselves? */
    public function allowsUserMode(Business $business, bool $withDraft = false): bool
    {
        return $this->effective($business, $withDraft)['user_mode'] ?? true;
    }

    /**
     * The :root CSS for a page: the business's mode, or the user's own
     * choice when the business allows it.
     */
    public function css(Business $business, ?User $user = null, bool $withDraft = false): string
    {
        $mode = $this->mode($business, $withDraft);
        if ($user && $this->allowsUserMode($business, $withDraft) && in_array($user->appearance, ThemeTokens::MODES, true)) {
            $mode = $user->appearance;
        }
        // The studio's light/dark switch.
        $forced = $withDraft ? ($this->studio[$business->id]['_appearance'] ?? null) : null;
        if (in_array($forced, ['light', 'dark'], true)) {
            $mode = $forced;
        }

        return ThemeTokens::css(
            $this->tokens($business, $withDraft),
            $mode === 'light' ? [] : $this->tokens($business, $withDraft, dark: true),
            $mode,
        );
    }

    /**
     * Readability problems of the draft (or what is live): light always,
     * dark too when anyone can end up in dark.
     *
     * @return list<array<string, mixed>>
     */
    public function contrast(Business $business, bool $withDraft = true): array
    {
        $issues = ThemeContrast::check($this->tokens($business, $withDraft), 'light');
        if ($this->mode($business, $withDraft) !== 'light' || $this->allowsUserMode($business, $withDraft)) {
            $issues = [...$issues, ...ThemeContrast::check($this->tokens($business, $withDraft, dark: true), 'dark')];
        }

        return $issues;
    }

    public function draft(Business $business): ?BrandingVersion
    {
        return $this->versions($business)->where('status', BrandingVersion::DRAFT)->first();
    }

    public function published(Business $business): ?BrandingVersion
    {
        return $this->versions($business)->where('status', BrandingVersion::PUBLISHED)->first();
    }

    /**
     * @return Collection<int, BrandingVersion>
     */
    public function history(Business $business): Collection
    {
        return $this->versions($business)->with(['publisher', 'author'])->orderByDesc('number')->get();
    }

    /**
     * Change part of the draft (starting one from what is live).
     *
     * @param  array<string, mixed>  $sections  e.g. ['colors' => [...]]; a section given replaces the draft's
     */
    public function saveDraft(Business $business, array $sections): array
    {
        $draft = $this->draft($business) ?? $this->newDraft($business, $this->published($business)?->payload ?? []);
        $draft->payload = ThemeSchema::clean(array_replace($draft->payload ?? [], $sections));
        $draft->save();

        return $this->success($draft, __('body.theme_draft_saved'));
    }

    public function publish(Business $business): array
    {
        $draft = $this->draft($business);
        if (! $draft) {
            return $this->failure(__('body.theme_nothing_to_publish'));
        }

        $blocking = array_filter($this->contrast($business), fn ($issue) => $issue['blocking']);
        if ($blocking) {
            return $this->failure(__('body.theme_unreadable', ['count' => count($blocking)]));
        }

        DB::transaction(function () use ($business, $draft) {
            $this->versions($business)->where('status', BrandingVersion::PUBLISHED)->get()
                ->each(fn (BrandingVersion $old) => $old->update(['status' => BrandingVersion::ARCHIVED]));

            $draft->status = BrandingVersion::PUBLISHED;
            $draft->published_at = now();
            $draft->published_by = auth()->id();
            $draft->save();
        });

        $this->forget($business);
        audit('branding_published', "Published branding version #{$draft->number}", $draft, [], $business->id);

        return $this->success($draft, __('body.theme_published'));
    }

    public function discard(Business $business): array
    {
        $draft = $this->draft($business);
        if ($draft) {
            $draft->delete();
            audit('branding_discarded', "Discarded branding draft #{$draft->number}", $draft, [], $business->id);
        }

        return $this->success($draft, __('body.theme_discarded'));
    }

    /** Copy an older version into the draft, to look at and publish. */
    public function restore(Business $business, BrandingVersion $version): array
    {
        abort_unless($version->business_id === $business->id, 404);

        $this->replaceDraft($business, $version->payload ?? []);
        audit('branding_restored', "Restored branding version #{$version->number} into the draft", $version, [], $business->id);

        return $this->success($version, __('body.theme_restored', ['number' => $version->number]));
    }

    /** A draft with nothing of its own: the platform's look. */
    public function reset(Business $business): array
    {
        $draft = $this->replaceDraft($business, []);
        audit('branding_reset', 'Reset branding to the platform defaults (draft)', $draft, [], $business->id);

        return $this->success($draft, __('body.theme_reset'));
    }

    /**
     * A preset's look into the draft: every appearance part is set (or cleared
     * to default) from it; dashboard, menu and notices are left alone.
     *
     * @param  array<string, mixed>  $preset
     */
    public function applyPreset(Business $business, array $preset, string $name): array
    {
        $sections = collect(ThemeSchema::PRESET_SECTIONS)->mapWithKeys(fn ($section) => [$section => $preset[$section] ?? null])->all();
        $result = $this->saveDraft($business, $sections);
        audit('branding_preset_applied', "Applied the branding preset \"{$name}\" to the draft", $result['data'], [], $business->id);

        return $this->success($result['data'], __('body.preset_applied', ['name' => $name]));
    }

    /** @return list<array{name: string, theme: array<string, mixed>}> */
    public function savedPresets(Business $business): array
    {
        return array_values(array_filter((array) app(BusinessSettings::class)->get('branding.presets', [], $business), fn ($preset) => is_array($preset) && isset($preset['name'], $preset['theme'])));
    }

    /** The draft's (or live) look, kept under a name. */
    public function savePreset(Business $business, string $name): array
    {
        $presets = array_values(array_filter($this->savedPresets($business), fn ($preset) => $preset['name'] !== $name));
        if (count($presets) >= ThemeSchema::MAX_SAVED_PRESETS) {
            return $this->failure(__('body.preset_limit', ['max' => ThemeSchema::MAX_SAVED_PRESETS]));
        }

        $theme = array_intersect_key($this->effective($business, withDraft: true), array_flip(ThemeSchema::PRESET_SECTIONS));
        $presets[] = ['name' => $name, 'theme' => $theme];
        app(BusinessSettings::class)->set('branding.presets', $presets, $business);

        return $this->success($presets, __('body.preset_saved', ['name' => $name]));
    }

    public function deletePreset(Business $business, int $index): array
    {
        $presets = $this->savedPresets($business);
        $name = $presets[$index]['name'] ?? null;
        if ($name === null) {
            return $this->failure(__('body.preset_missing'));
        }

        array_splice($presets, $index, 1);
        $presets === [] ? app(BusinessSettings::class)->forget('branding.presets', $business) : app(BusinessSettings::class)->set('branding.presets', $presets, $business);

        return $this->success($presets, __('body.preset_deleted', ['name' => $name]));
    }

    public function forget(Business $business): void
    {
        Cache::forget($this->cacheKey($business));
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedPayload(Business $business): array
    {
        return Cache::rememberForever($this->cacheKey($business), fn () => $this->published($business)?->payload ?? []);
    }

    private function replaceDraft(Business $business, array $payload): BrandingVersion
    {
        $draft = $this->draft($business) ?? $this->newDraft($business, []);
        $draft->payload = ThemeSchema::clean($payload);
        $draft->save();

        return $draft;
    }

    private function newDraft(Business $business, array $payload): BrandingVersion
    {
        $version = new BrandingVersion([
            'number' => (int) BrandingVersion::withoutGlobalScope('business')->withTrashed()->where('business_id', $business->id)->max('number') + 1,
            'status' => BrandingVersion::DRAFT,
            'payload' => $payload,
        ]);
        $version->business_id = $business->id;

        return $version;
    }

    /** Explicit business, never the request's: the platform theme is read for every business. */
    private function versions(Business $business): Builder
    {
        return BrandingVersion::withoutGlobalScope('business')->where('business_id', $business->id);
    }

    private function cacheKey(Business $business): string
    {
        return 'branding.theme.'.$business->id;
    }
}
