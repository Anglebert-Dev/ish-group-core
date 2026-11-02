<?php

namespace Tests\Feature;

use App\Services\Branding\ThemeService;
use Tests\TestCase;

/**
 * The branding studio's live preview (docs/branding.md §3.15, phase B4).
 */
class ThemeStudioTest extends TestCase
{
    private function studio(array $payload): string
    {
        return strtr(base64_encode(json_encode($payload)), '+/', '-_');
    }

    public function test_unsaved_choices_show_in_the_preview_and_nothing_is_saved(): void
    {
        $owner = $this->actingAsStaff();

        $this->get(route('dashboard', ['studio' => $this->studio(['colors' => ['card' => '#FAFAF0'], 'layout' => ['density' => 'compact']])]))->assertOk()
            ->assertSee('--c-card: 250 250 240', false)
            ->assertSee('data-density="compact"', false);

        $this->get(route('dashboard', ['studio' => $this->studio([]), 'appearance' => 'dark']))->assertOk()->assertSee('color-scheme:dark', false);

        $this->assertNull(app(ThemeService::class)->draft($owner->business));
        $this->get(route('dashboard'))->assertSee('--c-card: 255 255 255', false)->assertSee('data-density="comfortable"', false);
    }

    public function test_only_people_who_may_edit_branding_can_preview(): void
    {
        $owner = $this->makeStaff();
        $this->actingAs($this->makeMember($owner->business, ['list users.user']), 'staff');

        $this->get(route('dashboard', ['studio' => $this->studio(['colors' => ['card' => '#FAFAF0']])]))->assertOk()->assertSee('--c-card: 255 255 255', false);
        $this->get(route('settings.studio.components'))->assertForbidden();
    }

    public function test_bad_studio_input_is_ignored(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard', ['studio' => 'not-base64-json!!']))->assertOk()->assertSee('--c-card: 255 255 255', false);
        $this->get(route('dashboard', ['studio' => $this->studio(['colors' => ['card' => 'javascript:alert(1)']])]))->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_the_studio_pages_and_panel(): void
    {
        $this->actingAsStaff();

        $this->get(route('settings.index', ['tab' => 'theme']))->assertOk()->assertSee('id="studio-frame"', false)->assertSee(__('body.studio_page_login'));
        $this->get(route('settings.index', ['tab' => 'layout']))->assertOk()->assertSee('id="studio-frame"', false);
        $this->get(route('settings.studio.components'))->assertOk()->assertSee(__('body.studio_alert_warning'));
        $this->get(route('settings.studio.login'))->assertOk()->assertSee('name="password"', false);
        $this->get(route('settings.studio.email'))->assertOk()->assertSee(__('body.studio_email_line'));
    }
}
