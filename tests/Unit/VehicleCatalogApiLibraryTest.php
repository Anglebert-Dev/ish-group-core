<?php

namespace Tests\Unit;

use App\Libraries\VehicleCatalogApiLibrary;
use App\Libraries\VehicleCatalogLibrary;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleCatalogApiLibraryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nhtsa.enabled' => true]);
        VehicleCatalogApiLibrary::forgetCache();
        VehicleCatalogApiLibrary::forgetCache('Toyota');
    }

    public function test_fetches_and_normalizes_makes_from_nhtsa(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/*' => Http::response([
                'Count' => 2,
                'Results' => [
                    ['MakeName' => 'TOYOTA'],
                    ['MakeName' => 'MERCEDES-BENZ'],
                    ['MakeName' => 'BMW'],
                ],
            ], 200),
        ]);

        $makes = VehicleCatalogApiLibrary::makes();

        $this->assertContains('Toyota', $makes);
        $this->assertContains('Mercedes-Benz', $makes);
        $this->assertContains('BMW', $makes);
    }

    public function test_fetches_models_for_make(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMake/*' => Http::response([
                'Count' => 3,
                'Results' => [
                    ['Model_Name' => 'Corolla'],
                    ['Model_Name' => 'RAV4'],
                    ['Model_Name' => 'bZ4X'],
                ],
            ], 200),
        ]);

        $models = VehicleCatalogApiLibrary::modelsFor('Toyota');

        $this->assertSame(['bZ4X', 'Corolla', 'RAV4'], $models);
    }

    public function test_catalog_library_merges_api_models_with_fallback(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMake/*' => Http::response([
                'Count' => 1,
                'Results' => [
                    ['Model_Name' => 'Highlander'],
                ],
            ], 200),
        ]);

        // Warm the NHTSA cache; local catalog makes skip cold network waits.
        VehicleCatalogApiLibrary::modelsFor('Toyota');

        $models = VehicleCatalogLibrary::modelsFor('Toyota');

        $this->assertContains('Corolla', $models);
        $this->assertContains('Highlander', $models);
    }

    public function test_catalog_library_uses_static_models_without_waiting_on_api(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/*' => Http::response('error', 500),
        ]);

        $models = VehicleCatalogLibrary::modelsFor('Kia');

        $this->assertContains('Sorento', $models);
        $this->assertContains('Sportage', $models);
    }

    public function test_returns_empty_when_api_fails(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/*' => Http::response('error', 500),
        ]);

        $this->assertSame([], VehicleCatalogApiLibrary::makes());
        $this->assertSame([], VehicleCatalogApiLibrary::modelsFor('Toyota'));
    }
}
