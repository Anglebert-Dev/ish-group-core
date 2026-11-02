<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\Branding\BrandingVersion;
use App\Models\System\System;
use App\Services\Branding\ThemeService;
use Tests\TestCase;

/**
 * Theme drafts, publishing, preview and history (docs/branding.md §2.2).
 */
class ThemeVersionsTest extends TestCase
{
    public function test_a_draft_is_invisible_until_published(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'theme', 'colors' => ['card' => '#fafaf0', 'ink' => '']])
            ->assertRedirect(route('settings.index', ['tab' => 'theme']));

        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 255 255 255', false);
        $this->get(route('settings.index', ['tab' => 'theme']))->assertOk()->assertSee(__('body.theme_draft_title', ['number' => 1]));

        $this->post(route('settings.theme.publish'))->assertRedirect();

        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 250 250 240', false);
        $live = app(ThemeService::class)->published($owner->business);
        $this->assertSame(['colors' => ['card' => '#FAFAF0']], $live->payload, 'blank colours are not stored');
        $this->assertNull(app(ThemeService::class)->draft($owner->business));
        $this->assertTrue(ActivityLog::query()->where('description', 'Published branding version #1')->exists());
    }

    public function test_preview_shows_the_draft_to_this_user_only(): void
    {
        $owner = $this->actingAsStaff();
        $this->put(route('settings.update'), ['section' => 'theme', 'colors' => ['card' => '#FAFAF0']]);

        $this->post(route('settings.theme.preview'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 250 250 240', false)->assertSee(__('body.theme_previewing'));

        $this->post(route('settings.theme.preview.stop'));
        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 255 255 255', false)->assertDontSee(__('body.theme_previewing'));

        // Publishing ends the preview: what is live is what everyone sees.
        $this->post(route('settings.theme.preview'));
        $this->post(route('settings.theme.publish'));
        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 250 250 240', false)->assertDontSee(__('body.theme_previewing'));
    }

    public function test_preview_never_leaks_to_another_member(): void
    {
        $owner = $this->makeStaff();
        app(ThemeService::class)->saveDraft($owner->business, ['colors' => ['card' => '#FAFAF0']]);
        $member = $this->makeMember($owner->business, ['list users.user']);

        // Even with the session flag, someone who can't edit branding sees the live look.
        $this->actingAs($member, 'staff')->withSession([ThemeService::PREVIEW => $owner->business->id]);
        $this->get(route('dashboard'))->assertOk()->assertSee('--c-card: 255 255 255', false);
    }

    public function test_publishing_needs_the_approve_permission(): void
    {
        $owner = $this->makeStaff();
        $editor = $this->makeMember($owner->business, ['list settings.setting', 'update settings.setting']);
        $this->actingAs($editor, 'staff');

        $this->put(route('settings.update'), ['section' => 'theme', 'colors' => ['card' => '#FAFAF0']])->assertSessionHasNoErrors();
        $this->post(route('settings.theme.publish'))->assertForbidden();
        $this->assertNotNull(app(ThemeService::class)->draft($owner->business));
    }

    public function test_history_restore_discard_and_reset(): void
    {
        $owner = $this->actingAsStaff();
        $themes = app(ThemeService::class);

        foreach (['#FAFAF0', '#F0F0FA'] as $card) {
            $themes->saveDraft($owner->business, ['colors' => ['card' => $card]]);
            $this->post(route('settings.theme.publish'));
        }
        $first = BrandingVersion::query()->where('number', 1)->firstOrFail();
        $this->assertSame(BrandingVersion::ARCHIVED, $first->status);

        $this->get(route('settings.index', ['tab' => 'history']))->assertOk()->assertSee(__('body.theme_version', ['number' => 2]));

        $this->post(route('settings.theme.restore', $first))->assertRedirect();
        $this->assertSame('#FAFAF0', $themes->draft($owner->business)->payload['colors']['card']);
        $this->post(route('settings.theme.publish'));
        $this->assertSame('250 250 240', implode(' ', sscanf($themes->tokens($owner->business)['card'], '#%2x%2x%2x')));

        $this->post(route('settings.theme.reset'));
        $this->assertSame([], $themes->draft($owner->business)->payload);
        $this->post(route('settings.theme.discard'));
        $this->assertNull($themes->draft($owner->business));
        $this->assertSame('#FAFAF0', $themes->tokens($owner->business)['card'], 'discarding keeps what is live');
    }

    public function test_the_platform_look_is_the_default_for_every_business(): void
    {
        [$platform] = $this->makeBusiness();
        System::setProperty('primary_business_id', $platform->id);
        $themes = app(ThemeService::class);
        $themes->saveDraft($platform, ['colors' => ['sidebar' => '#0B1F3A', 'sidebar_ink' => '#E2E8F0', 'card' => '#FDFDFD']]);
        $themes->publish($platform);

        [$tenant] = $this->makeBusiness();
        $this->assertSame('#0B1F3A', $themes->tokens($tenant)['sidebar']);

        $themes->saveDraft($tenant, ['colors' => ['sidebar' => '#222222', 'sidebar_ink' => '#F5F5F5']]);
        $themes->publish($tenant);
        $this->assertSame('#222222', $themes->tokens($tenant)['sidebar'], 'its own choice wins');
        $this->assertSame('#FDFDFD', $themes->tokens($tenant)['card'], 'the platform fills the rest');
    }

    public function test_versions_stay_inside_their_business(): void
    {
        [$other] = $this->makeBusiness();
        app(ThemeService::class)->saveDraft($other, ['colors' => ['card' => '#FAFAF0']]);
        app(ThemeService::class)->publish($other);
        $theirs = BrandingVersion::withoutGlobalScope('business')->where('business_id', $other->id)->firstOrFail();

        $this->actingAsStaff();
        $this->post(route('settings.theme.restore', $theirs->uuid))->assertNotFound();
    }

    public function test_bad_colours_are_refused(): void
    {
        $this->actingAsStaff();

        $this->put(route('settings.update'), ['section' => 'theme', 'colors' => ['card' => 'red']])->assertSessionHasErrors('colors.card');
        $this->put(route('settings.update'), ['section' => 'theme', 'colors' => ['brand' => '#000000', 'card' => '#FFFFFF']])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('brand', app(ThemeService::class)->draft(auth('staff')->user()->business)->payload['colors'], 'brand colour stays on Look');
    }
}
