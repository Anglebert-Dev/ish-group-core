<?php

namespace Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Models\Role\Role;
use App\Models\System\System;
use App\Models\User;
use Tests\TestCase;

class BusinessDeletionTest extends TestCase
{
    public function test_superadmin_deletes_a_business_and_its_data(): void
    {
        $super = $this->makeSuperAdmin();
        System::addProperty('primary_business_id', $super->business_id);
        [$client, $owner] = $this->makeBusiness(['business_name' => 'Doomed Co']);
        $member = $this->makeMember($client);

        $this->actingAs($super, 'staff');

        $this->withConfirmedPassword()
            ->delete(route('superadmin.businesses.destroy', $client), ['confirm_name' => 'Doomed Co'])
            ->assertRedirect(route('superadmin.businesses.index'))
            ->assertSessionHas('success');

        $this->assertNull(Business::withTrashed()->find($client->id));
        $this->assertNull(User::withTrashed()->find($owner->id));
        $this->assertNull(User::withTrashed()->find($member->id));
        $this->assertSame(0, Role::query()->where('business_id', $client->id)->count());
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $owner->id]);
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()
            ->where('business_id', $super->business_id)
            ->where('subject_label', 'Doomed Co')->exists());

        // Their email is free again.
        $this->makeBusiness(['email' => $owner->email]);
    }

    public function test_name_must_be_typed_exactly(): void
    {
        $super = $this->makeSuperAdmin();
        [$client] = $this->makeBusiness(['business_name' => 'Keep Me']);
        $this->actingAs($super, 'staff');

        $this->withConfirmedPassword()
            ->delete(route('superadmin.businesses.destroy', $client), ['confirm_name' => 'keep me'])
            ->assertSessionHas('error');

        $this->assertNotNull($client->fresh());
    }

    public function test_cannot_delete_own_primary_or_superadmin_business(): void
    {
        $super = $this->makeSuperAdmin();
        [$primary] = $this->makeBusiness(['business_name' => 'Primary']);
        System::addProperty('primary_business_id', $primary->id);
        $otherSuper = $this->makeSuperAdmin(['business_name' => 'Has Super']);

        $this->actingAs($super, 'staff');

        foreach ([[$super->business, $super->business->name], [$primary, 'Primary'], [$otherSuper->business, 'Has Super']] as [$business, $name]) {
            $this->withConfirmedPassword()
                ->delete(route('superadmin.businesses.destroy', $business), ['confirm_name' => $name])
                ->assertSessionHas('error');
            $this->assertNotNull($business->fresh());
        }
    }

    public function test_business_admin_cannot_delete_businesses(): void
    {
        $this->actingAsStaff();
        [$other] = $this->makeBusiness(['business_name' => 'Other']);

        $this->withConfirmedPassword()
            ->delete(route('superadmin.businesses.destroy', $other), ['confirm_name' => 'Other'])
            ->assertForbidden();
    }
}
