<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Notification\Notification;
use App\Services\Notification\NotificationService;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    public function test_admin_can_open_core_screens(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard'))->assertOk()->assertSee(__('body.quick_actions'), false);
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk();
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('roles.create'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('activity-logs.index'))->assertOk();
        $this->get(route('settings.index'))->assertOk();
        $this->get(route('staff.profile'))->assertOk();
    }

    public function test_admin_updates_business_branding(): void
    {
        $owner = $this->actingAsStaff();

        $this->put(route('settings.update'), [
            'name' => 'Renamed Co',
            'brand_color' => '#1a2b3c',
        ])->assertRedirect(route('settings.index'));

        $business = $owner->business->fresh();
        $this->assertSame('Renamed Co', $business->name);
        $this->assertSame('#1A2B3C', $business->brand_color);
    }

    public function test_staff_can_mark_a_notification_as_read(): void
    {
        $user = $this->actingAsStaff();

        $notification = app(NotificationService::class)
            ->notify($user, 'Hello', 'World', NotificationType::Info)['data'];

        $this->assertInstanceOf(Notification::class, $notification);
        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertSame(NotificationStatus::Read, $notification->fresh()->status);
    }

    public function test_permission_notifications_stay_inside_one_business(): void
    {
        [$businessA, $ownerA] = $this->makeBusiness();
        [, $ownerB] = $this->makeBusiness();

        $sent = app(NotificationService::class)
            ->notifyByPermission('list users.user', 'Heads up', businessId: $businessA->id);

        $this->assertSame(1, $sent['data']);
        $this->assertSame(1, Notification::query()->where('user_id', $ownerA->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $ownerB->id)->count());
    }
}
