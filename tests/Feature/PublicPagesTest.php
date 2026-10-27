<?php

namespace Tests\Feature;

use App\Enums\VehicleStatus;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_home_page_loads(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_marketing_pages_load(): void
    {
        $this->get(route('about'))->assertOk();
        $this->get(route('contact'))->assertOk();
        $this->get(route('services'))->assertOk();
        $this->get(route('privacy'))->assertOk()->assertSee(__('body.privacy_policy'));
        $this->get(route('terms'))->assertOk()->assertSee(__('body.terms_and_conditions'));
    }

    public function test_vehicle_listing_and_available_detail_page(): void
    {
        $vehicle = $this->makeVehicle();

        $this->get(route('vehicles.index'))
            ->assertOk()
            ->assertSee('Toyota');

        $this->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Corolla');
    }

    public function test_inactive_vehicle_is_hidden_from_public_show(): void
    {
        $vehicle = $this->makeVehicle(overrides: ['status' => VehicleStatus::Inactive->value, 'plate_number' => 'RAD999Z']);

        $this->get(route('vehicles.show', $vehicle))->assertNotFound();
    }

    public function test_booking_search_page_loads(): void
    {
        $this->makeVehicle();

        $this->get(route('booking.search', [
            'pickup_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(4)->toDateString(),
        ]))->assertOk();
    }

    public function test_vehicle_search_matches_make_model_category_and_plate(): void
    {
        $economy = $this->makeCategory(['name' => 'Economy']);
        $suv = $this->makeCategory(['name' => 'SUV']);

        $corolla = $this->makeVehicle($economy, overrides: [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'plate_number' => 'RAD111A',
        ]);
        $ranger = $this->makeVehicle($suv, overrides: [
            'make' => 'Ford',
            'model' => 'Ranger',
            'plate_number' => 'RAD222B',
        ]);

        $this->get(route('vehicles.index', ['search' => 'Toyota']))
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertDontSee('Ford Ranger');

        $this->get(route('vehicles.index', ['search' => 'ranger']))
            ->assertOk()
            ->assertSee('Ford Ranger')
            ->assertDontSee('Toyota Corolla');

        $this->get(route('vehicles.index', ['search' => 'Toyota Corolla']))
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertDontSee('Ford Ranger');

        $this->get(route('vehicles.index', ['search' => 'SUV']))
            ->assertOk()
            ->assertSee('Ford Ranger')
            ->assertDontSee('Toyota Corolla');

        $this->get(route('vehicles.index', ['search' => 'RAD111A']))
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertDontSee('Ford Ranger');

        $this->get(route('vehicles.index', ['search' => 'no-such-vehicle']))
            ->assertOk()
            ->assertSee(__('body.no_vehicles_found'));

        $this->assertNotNull($corolla->id);
        $this->assertNotNull($ranger->id);
    }

    public function test_vehicle_search_works_with_other_filters_and_on_booking_search(): void
    {
        $economy = $this->makeCategory(['name' => 'Economy']);
        $this->makeVehicle($economy, overrides: [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'transmission' => \App\Enums\TransmissionType::Automatic->value,
            'daily_price' => '40000.00',
        ]);
        $this->makeVehicle($economy, overrides: [
            'make' => 'Toyota',
            'model' => 'Hilux',
            'transmission' => \App\Enums\TransmissionType::Manual->value,
            'daily_price' => '90000.00',
        ]);

        $this->get(route('vehicles.index', [
            'search' => 'Toyota',
            'transmission' => \App\Enums\TransmissionType::Automatic->value,
            'max_price' => 50000,
        ]))
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertDontSee('Toyota Hilux');

        $this->get(route('booking.search', [
            'category' => $economy->uuid,
        ]))
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertSee('Toyota Hilux');

        $this->get(route('booking.search', [
            'search' => 'Hilux',
            'pickup_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Toyota Hilux')
            ->assertDontSee('Toyota Corolla');
    }

    public function test_guest_is_redirected_from_customer_booking_details(): void
    {
        $this->get(route('booking.details'))->assertRedirect(route('login'));
    }
}
