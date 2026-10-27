<?php

namespace Tests\Feature;

use App\Models\Announcement\Announcement;
use App\Notifications\BrandedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailAndCommunicatorTest extends TestCase
{
    public function test_staff_reset_email_is_sent_in_the_business_name(): void
    {
        Notification::fake();
        [$business, $owner] = $this->makeBusiness(['business_name' => 'Kigali Bakery', 'email' => 'boss@example.com']);
        $business->forceFill(['email' => 'hello@bakery.example'])->save();

        $this->post(route('staff.password.email'), ['email' => 'boss@example.com']);

        Notification::assertSentTo($owner, ResetPassword::class, function ($notification) use ($owner) {
            $mail = $notification->toMail($owner);

            return $mail->from[1] === 'Kigali Bakery'
                && $mail->replyTo[0][0] === 'hello@bakery.example'
                && str_contains($mail->actionUrl, '/app/reset-password/');
        });
    }

    public function test_business_contact_email_is_saved_from_settings(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), ['name' => 'Renamed', 'brand_color' => '#123456', 'email' => 'replies@example.com'])
            ->assertRedirect(route('settings.index'));

        $this->assertSame('replies@example.com', $owner->business->fresh()->contactEmail());
    }

    public function test_communicator_sends_to_admins_of_chosen_businesses(): void
    {
        Notification::fake();
        $this->actingAs($this->makeSuperAdmin(), 'staff');
        [$alpha, $alphaOwner] = $this->makeBusiness();
        [, $bravoOwner] = $this->makeBusiness();
        $member = $this->makeMember($alpha);

        $this->get(route('superadmin.communicator.index'))->assertOk();

        $this->post(route('superadmin.communicator.send'), [
            'audience' => 'selected',
            'business_ids' => [$alpha->id],
            'subject' => 'Maintenance tonight',
            'body' => 'We will be down for 10 minutes.',
            'send_email' => '1',
            'send_in_app' => '1',
        ])->assertRedirect(route('superadmin.communicator.index'))->assertSessionHas('success');

        Notification::assertSentTo($alphaOwner, BrandedNotification::class);
        Notification::assertNotSentTo($bravoOwner, BrandedNotification::class);
        Notification::assertNotSentTo($member, BrandedNotification::class);
        $this->assertDatabaseHas('notifications', ['user_id' => $alphaOwner->id, 'title' => 'Maintenance tonight']);
        $this->assertSame(1, Announcement::query()->first()->recipients_count);
    }

    public function test_communicator_is_superadmin_only(): void
    {
        $this->actingAsStaff();

        $this->get(route('superadmin.communicator.index'))->assertForbidden();
    }

    public function test_branded_mail_renders(): void
    {
        [$business] = $this->makeBusiness(['business_name' => 'Render Co']);

        $html = (new BrandedNotification($business, 'Hello', ['Line one'], 'Open', 'https://example.com'))
            ->toMail(new AnonymousNotifiable)
            ->render();

        $this->assertStringContainsString('Render Co', (string) $html);
        $this->assertStringContainsString('https://example.com', (string) $html);
    }
}
