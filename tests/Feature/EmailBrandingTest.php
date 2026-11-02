<?php

namespace Tests\Feature;

use App\Support\Mail\BrandedMail;
use App\Support\Settings\BusinessSettings;
use Tests\TestCase;

/**
 * Email branding (docs/branding.md §3.10, phase B7).
 */
class EmailBrandingTest extends TestCase
{
    public function test_the_emails_tab_shapes_every_email(): void
    {
        $owner = $this->actingAsStaff();
        app(BusinessSettings::class)->set('branding.phone', '+250 788 300 400', $owner->business);

        $this->get(route('settings.index', ['tab' => 'emails']))->assertOk()->assertSee('id="email-preview"', false);
        $this->put(route('settings.update'), [
            'section' => 'emails', 'email_header_color' => 'rgb(15, 39, 68)', 'email_sender_name' => 'Kigali Sun Support',
            'email_reply_to' => 'help@kigalisun.test', 'email_footer' => 'Open Mon–Sat, 8:00–18:00', 'email_signature' => "Kind regards,\nThe team",
            'email_show_contact' => '1',
        ])->assertSessionHasNoErrors();

        $mail = BrandedMail::make($owner->business->fresh(), 'Hello', ['Your order is ready.'], 'View', 'https://example.test');
        $html = (string) $mail->render();

        $this->assertSame('Kigali Sun Support', $mail->from[1]);
        $this->assertSame('help@kigalisun.test', $mail->replyTo[0][0]);
        $this->assertStringContainsString('background:#0F2744', $html);
        $this->assertStringContainsString('Open Mon–Sat, 8:00–18:00', $html);
        $this->assertStringContainsString('Kind regards,<br />', $html);
        $this->assertStringContainsString('+250 788 300 400', $html);

        $this->put(route('settings.update'), ['section' => 'emails', 'email_header_color' => 'banana'])->assertSessionHasErrors('email_header_color');
    }

    public function test_button_text_stays_readable_on_a_light_brand(): void
    {
        [$business] = $this->makeBusiness();
        $business->update(['brand_color' => '#FDE047']);

        $html = (string) BrandedMail::make($business->fresh(), 'Hi', ['Line'], 'Open', 'https://example.test')->render();

        $this->assertStringContainsString('background:#FDE047;color:#111827;', $html);
    }

    public function test_the_preview_shows_unsaved_choices_and_ignores_half_typed_ones(): void
    {
        $this->actingAsStaff();

        $this->get(route('settings.studio.email', ['email' => ['signature' => 'Unsaved signature', 'header_color' => '#123456']]))->assertOk()
            ->assertSee('Unsaved signature')->assertSee('background:#123456', false);
        $this->get(route('settings.studio.email', ['email' => ['header_color' => '#12']]))->assertOk()->assertSee(__('body.studio_email_line'));
    }
}
