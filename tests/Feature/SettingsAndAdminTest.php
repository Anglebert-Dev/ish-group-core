<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Notification\Notification;
use App\Models\Setting\Setting;
use App\Services\Notification\NotificationService;
use Tests\TestCase;

class SettingsAndAdminTest extends TestCase
{
    public function test_staff_can_update_branding_settings(): void
    {
        $this->actingAsStaff();

        $this->get(route('settings.index'))->assertOk();

        $this->put(route('settings.update'), [
            'company_name' => 'Africa Drive',
            'brand_color' => '#1a2b3c',
        ])->assertRedirect(route('settings.index'));

        $setting = Setting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame('Africa Drive', $setting->company_name);
        $this->assertSame('#1A2B3C', $setting->brand_color);
    }

    public function test_staff_can_open_users_roles_notifications_and_activity_logs(): void
    {
        $this->actingAsStaff();

        $this->get(route('users.index'))->assertOk();
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('activity-logs.index'))->assertOk();
        $this->get(route('staff.profile'))->assertOk();
        $this->get(route('customers.index'))->assertOk();
        $this->get(route('drivers.index'))->assertOk();
        $this->get(route('rentals.index'))->assertOk();
        $this->get(route('chauffeur.index'))->assertOk();
        $this->get(route('bookings.index'))->assertOk();
    }

    public function test_staff_can_mark_a_notification_as_read(): void
    {
        $user = $this->actingAsStaff();

        $result = app(NotificationService::class)->notify(
            $user,
            'Booking confirmed',
            'AFR-202609-000001 is ready',
            NotificationType::Booking,
        );

        $notification = $result['data'];
        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertSame(NotificationStatus::Unread, $notification->status);

        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertSame(NotificationStatus::Read, $notification->fresh()->status);
    }
}
