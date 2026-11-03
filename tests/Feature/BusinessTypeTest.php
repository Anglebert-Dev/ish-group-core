<?php

namespace Tests\Feature;

use App\Models\Business\Business;
use Tests\TestCase;

class BusinessTypeTest extends TestCase
{
    public function test_superadmin_creates_a_business_with_a_type_and_changes_it(): void
    {
        $this->actingAs($this->makeSuperAdmin(), 'staff');

        $this->get(route('superadmin.businesses.create'))->assertOk()->assertSee('name="type"', false)->assertSee(__('body.business_type_fuel_station'));

        $this->withConfirmedPassword()->post(route('superadmin.businesses.store'), [
            'business_name' => 'Kigali Fuel', 'type' => 'fuel_station',
            'name' => 'Owner', 'email' => 'fuel@example.com', 'password' => self::TEST_PASSWORD, 'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect();

        $business = Business::query()->where('name', 'Kigali Fuel')->firstOrFail();
        $this->assertSame('fuel_station', $business->type);
        $this->assertSame('Fuel station', $business->typeLabel());

        $this->put(route('superadmin.businesses.type', $business), ['type' => 'hospital'])->assertRedirect();
        $this->assertSame('hospital', $business->fresh()->type);

        $this->put(route('superadmin.businesses.type', $business), ['type' => 'not_a_type'])->assertSessionHasErrors('type');
    }

    public function test_only_superadmins_change_the_type(): void
    {
        [$business, $owner] = $this->makeBusiness();

        $this->actingAs($owner, 'staff')->put(route('superadmin.businesses.type', $business), ['type' => 'hospital']);
        $this->assertSame('other', $business->fresh()->type);
    }
}
