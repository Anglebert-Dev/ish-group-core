<?php

namespace Database\Seeders;

use App\Enums\CorporateCustomerStatus;
use App\Enums\CustomerStatus;
use App\Enums\FuelLevel;
use App\Enums\FuelLogStatus;
use App\Enums\FuelType as FuelTypeEnum;
use App\Enums\LicenceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\PaymentOption;
use App\Enums\RentalType;
use App\Enums\VehicleOwnerStatus;
use App\Models\Booking\Booking;
use App\Models\CorporateCustomer\CorporateContact;
use App\Models\CorporateCustomer\CorporateCustomer;
use App\Models\Customer;
use App\Models\Driver\Driver;
use App\Models\Extra\Extra;
use App\Models\FuelLog\FuelLog;
use App\Models\Station\Station;
use App\Models\Vehicle\Vehicle;
use App\Models\VehicleCategory\VehicleCategory;
use App\Models\VehicleMaintenance\VehicleMaintenance;
use App\Models\VehicleOwner\VehicleOwner;
use App\Services\Booking\BookingService;
use App\Services\Rental\RentalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Fills the gaps DemoFleetSeeder leaves open — customers, corporate accounts,
 * vehicle owners, compliance dates, fuel/maintenance history, and bookings
 * driven through the real BookingService/RentalService so GL postings,
 * invoices, and vehicle status stay consistent with normal app usage.
 */
class DemoBookingSeeder extends Seeder
{
    private BookingService $bookingService;

    private RentalService $rentalService;

    public function run(): void
    {
        $this->bookingService = app(BookingService::class);
        $this->rentalService = app(RentalService::class);

        $customers = $this->seedCustomers();
        $corporates = $this->seedCorporateCustomers();
        $this->seedVehicleOwners();
        $this->seedComplianceDates();
        $this->seedFuelLogs();
        $this->seedMaintenance();

        if (Booking::query()->exists()) {
            return;
        }

        $realNow = Carbon::now();

        try {
            $this->seedBookings($customers, $corporates);
        } finally {
            Carbon::setTestNow($realNow);
        }
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(): array
    {
        $rows = [
            ['name' => 'Emmanuel Bizimana', 'email' => 'emmanuel.bizimana@example.com', 'phone' => '+250 788 300 001', 'national_id' => '1198780012345001', 'address' => 'Kimihurura, Kigali', 'driver_license_number' => 'RW-DL-2001', 'status' => CustomerStatus::Active, 'password' => 'password'],
            ['name' => 'Solange Uwamahoro', 'email' => 'solange.uwamahoro@example.com', 'phone' => '+250 788 300 002', 'national_id' => '1198780012345002', 'address' => 'Kicukiro, Kigali', 'driver_license_number' => 'RW-DL-2002', 'status' => CustomerStatus::Active, 'password' => 'password'],
            ['name' => 'Innocent Nshimiyimana', 'email' => 'innocent.nshimiyimana@example.com', 'phone' => '+250 788 300 003', 'national_id' => '1198780012345003', 'address' => 'Kacyiru, Kigali', 'driver_license_number' => 'RW-DL-2003', 'status' => CustomerStatus::Active, 'password' => null],
            ['name' => 'Marie Claire Ingabire', 'email' => 'marieclaire.ingabire@example.com', 'phone' => '+250 788 300 004', 'national_id' => '1198780012345004', 'address' => 'Remera, Kigali', 'driver_license_number' => 'RW-DL-2004', 'status' => CustomerStatus::Active, 'password' => 'password'],
            ['name' => 'Fabrice Rugamba', 'email' => 'fabrice.rugamba@example.com', 'phone' => '+250 788 300 005', 'national_id' => '1198780012345005', 'address' => 'Nyamirambo, Kigali', 'driver_license_number' => 'RW-DL-2005', 'status' => CustomerStatus::Active, 'password' => null],
            ['name' => 'Yvette Mukashyaka', 'email' => 'yvette.mukashyaka@example.com', 'phone' => '+250 788 300 006', 'national_id' => '1198780012345006', 'address' => 'Gisozi, Kigali', 'driver_license_number' => 'RW-DL-2006', 'status' => CustomerStatus::Pending, 'password' => null],
            ['name' => 'Olivier Ndayisenga', 'email' => 'olivier.ndayisenga@example.com', 'phone' => '+250 788 300 007', 'national_id' => '1198780012345007', 'address' => 'Nyarugenge, Kigali', 'driver_license_number' => 'RW-DL-2007', 'status' => CustomerStatus::Banned, 'password' => null],
            ['name' => 'Grace Umutoni', 'email' => 'grace.umutoni@example.com', 'phone' => '+250 788 300 008', 'national_id' => '1198780012345008', 'address' => 'Rubavu', 'driver_license_number' => 'RW-DL-2008', 'status' => CustomerStatus::Active, 'password' => 'password'],
        ];

        $customers = [];

        foreach ($rows as $row) {
            $password = $row['password'];
            $status = $row['status'];
            unset($row['password'], $row['status']);

            $customer = Customer::query()->firstOrNew(['email' => $row['email']]);
            $customer->fill($row);
            $customer->status = $status;

            if ($password && ! $customer->hasPortalPassword()) {
                $customer->password = Hash::make($password);
            }

            $customer->save();
            $customers[$row['name']] = $customer;
        }

        return $customers;
    }

    /**
     * @return array<string, CorporateCustomer>
     */
    private function seedCorporateCustomers(): array
    {
        $rows = [
            [
                'company_name' => 'Rwanda Tech Solutions Ltd',
                'tin' => '102345678',
                'email' => 'accounts@rwandatechsolutions.example.com',
                'phone' => '+250 788 400 001',
                'address' => 'KG 7 Ave, Kigali',
                'billing_address' => 'KG 7 Ave, Kigali',
                'payment_terms' => 'Net 30',
                'credit_limit' => 2000000,
                'contacts' => [
                    'primary' => ['name' => 'David Habyarimana', 'email' => 'david@rwandatechsolutions.example.com', 'phone' => '+250 788 400 011'],
                    'finance' => ['name' => 'Chantal Mukamurenzi', 'email' => 'finance@rwandatechsolutions.example.com', 'phone' => '+250 788 400 012'],
                ],
            ],
            [
                'company_name' => 'Inyange Logistics Ltd',
                'tin' => '102345679',
                'email' => 'info@inyangelogistics.example.com',
                'phone' => '+250 788 400 002',
                'address' => 'Nyabugogo, Kigali',
                'billing_address' => 'Nyabugogo, Kigali',
                'payment_terms' => 'Net 15',
                'credit_limit' => 1500000,
                'contacts' => [
                    'primary' => ['name' => 'Vincent Twagirayezu', 'email' => 'vincent@inyangelogistics.example.com', 'phone' => '+250 788 400 021'],
                    'booking' => ['name' => 'Alice Uwimbabazi', 'email' => 'bookings@inyangelogistics.example.com', 'phone' => '+250 788 400 022'],
                ],
            ],
            [
                'company_name' => 'Kigali Heights Hotel',
                'tin' => '102345680',
                'email' => 'reservations@kigaliheightshotel.example.com',
                'phone' => '+250 788 400 003',
                'address' => 'KG 7 Ave, Kigali',
                'billing_address' => 'KG 7 Ave, Kigali',
                'payment_terms' => 'Net 30',
                'credit_limit' => 3000000,
                'contacts' => [
                    'primary' => ['name' => 'Beatrice Nyiraneza', 'email' => 'beatrice@kigaliheightshotel.example.com', 'phone' => '+250 788 400 031'],
                    'booking' => ['name' => 'Samuel Byiringiro', 'email' => 'frontdesk@kigaliheightshotel.example.com', 'phone' => '+250 788 400 032'],
                    'finance' => ['name' => 'Diane Uwase', 'email' => 'finance@kigaliheightshotel.example.com', 'phone' => '+250 788 400 033'],
                ],
            ],
        ];

        $corporates = [];

        foreach ($rows as $row) {
            $contacts = $row['contacts'];
            unset($row['contacts']);

            $corporate = CorporateCustomer::query()->firstOrNew(['company_name' => $row['company_name']]);
            $corporate->fill($row);
            $corporate->status = CorporateCustomerStatus::Active;
            $corporate->save();

            foreach ($contacts as $type => $contact) {
                CorporateContact::query()->updateOrCreate(
                    ['corporate_customer_id' => $corporate->id, 'type' => $type],
                    $contact,
                );
            }

            $corporates[$row['company_name']] = $corporate;
        }

        return $corporates;
    }

    private function seedVehicleOwners(): void
    {
        $rows = [
            [
                'first_name' => 'Jean-Baptiste', 'last_name' => 'Rugema',
                'phone' => '+250 788 500 001', 'email' => 'jb.rugema@example.com',
                'address' => 'Kacyiru, Kigali', 'national_id' => '1198570012345101',
                'owner_share_percent' => 70, 'company_share_percent' => 30,
                'agreement_starts_on' => '2025-01-01', 'plates' => ['RAD 103 A', 'RAD 201 A'],
            ],
            [
                'first_name' => 'Immaculee', 'last_name' => 'Nyirahabimana',
                'phone' => '+250 788 500 002', 'email' => 'immaculee.n@example.com',
                'address' => 'Musanze', 'national_id' => '1198570012345102',
                'owner_share_percent' => 65, 'company_share_percent' => 35,
                'agreement_starts_on' => '2025-03-15', 'plates' => ['RAD 401 A'],
            ],
            [
                'first_name' => 'Robert', 'last_name' => 'Mugisha',
                'phone' => '+250 788 500 003', 'email' => 'robert.mugisha@example.com',
                'address' => 'Rubavu', 'national_id' => '1198570012345103',
                'owner_share_percent' => 75, 'company_share_percent' => 25,
                'agreement_starts_on' => '2025-02-01', 'plates' => ['RAD 202 A'],
            ],
        ];

        foreach ($rows as $row) {
            $plates = $row['plates'];
            unset($row['plates']);

            $owner = VehicleOwner::query()->firstOrNew(['email' => $row['email']]);
            $owner->fill($row);
            $owner->status = VehicleOwnerStatus::Active;
            $owner->save();

            Vehicle::query()->whereIn('plate_number', $plates)->update(['vehicle_owner_id' => $owner->id]);
        }
    }

    private function seedComplianceDates(): void
    {
        // today is 2026-09-30 in this environment: mix of Red (<=15d), Yellow (<=30d), and safe dates.
        $rows = [
            'RAD 101 A' => ['insurance_expires_at' => '2026-10-05', 'inspection_done_at' => '2026-04-01', 'inspection_expires_at' => '2026-12-01'],
            'RAD 102 A' => ['insurance_expires_at' => '2026-10-20', 'inspection_done_at' => '2026-04-10', 'inspection_expires_at' => '2026-10-10'],
            'RAD 103 A' => ['insurance_expires_at' => '2027-03-01', 'inspection_done_at' => '2026-05-01', 'inspection_expires_at' => '2027-02-01'],
            'RAD 104 A' => ['insurance_expires_at' => '2027-01-15', 'inspection_done_at' => '2026-05-15', 'inspection_expires_at' => '2027-01-15'],
            'RAD 105 A' => ['insurance_expires_at' => '2026-10-12', 'inspection_done_at' => '2026-03-01', 'inspection_expires_at' => '2026-10-25'],
            'RAD 201 A' => ['insurance_expires_at' => '2026-10-12', 'inspection_done_at' => '2026-06-01', 'inspection_expires_at' => '2026-10-25'],
            'RAD 202 A' => ['insurance_expires_at' => '2027-04-01', 'inspection_done_at' => '2026-06-15', 'inspection_expires_at' => '2027-03-01'],
            'RAD 203 A' => ['insurance_expires_at' => '2027-02-20', 'inspection_done_at' => '2026-07-01', 'inspection_expires_at' => '2027-02-01'],
            'RAD 301 A' => ['insurance_expires_at' => '2026-12-01', 'inspection_done_at' => '2026-07-15', 'inspection_expires_at' => '2026-12-15'],
            'RAD 401 A' => ['insurance_expires_at' => '2027-05-01', 'inspection_done_at' => '2026-08-01', 'inspection_expires_at' => '2027-04-01'],
        ];

        foreach ($rows as $plate => $dates) {
            Vehicle::query()->where('plate_number', $plate)->update([
                'insurance_company' => 'Sonarwa General Insurance',
                ...$dates,
                'carte_jaune_number' => 'CJ-'.str_replace(' ', '', $plate),
                'carte_jaune_issued_at' => '2026-01-01',
            ]);
        }
    }

    private function seedFuelLogs(): void
    {
        if (FuelLog::query()->exists()) {
            return;
        }

        $plates = ['RAD 101 A', 'RAD 102 A', 'RAD 103 A', 'RAD 201 A', 'RAD 202 A', 'RAD 301 A', 'RAD 401 A'];
        $drivers = Driver::query()->pluck('id')->all();
        $stations = ['Kabuga Fuel Station', 'Total Kimironko', 'Engen Nyabugogo', 'Rubavu Fuel Point'];

        $day = Carbon::parse('2026-09-05');

        foreach ($plates as $index => $plate) {
            $vehicle = Vehicle::query()->where('plate_number', $plate)->first();

            if (! $vehicle) {
                continue;
            }

            for ($i = 0; $i < 2; $i++) {
                $day = $day->copy()->addDays(2);

                FuelLog::create([
                    'vehicle_id' => $vehicle->id,
                    'gas_station_name' => $stations[($index + $i) % count($stations)],
                    'driver_id' => $drivers[($index + $i) % count($drivers)] ?? null,
                    'fuel_type' => $vehicle->fuel_type ?? FuelTypeEnum::Petrol,
                    'date' => $day->toDateString(),
                    'liters' => $i === 0 ? 35 : 42,
                    'cost' => $i === 0 ? 42000 : 50400,
                    'mileage' => ($vehicle->mileage ?? 10000) + ($i * 300),
                    'status' => $index === 0 && $i === 1 ? FuelLogStatus::Voided : FuelLogStatus::Recorded,
                    'notes' => $i === 0 ? 'Routine refuel.' : null,
                ]);
            }
        }
    }

    private function seedMaintenance(): void
    {
        if (VehicleMaintenance::query()->exists()) {
            return;
        }

        $rows = [
            ['plate' => 'RAD 105 A', 'type' => MaintenanceType::Repair, 'description' => 'Suspension repair after unusual noise reported by renter.', 'date' => '2026-09-28', 'cost' => 180000, 'status' => MaintenanceStatus::InProgress],
            ['plate' => 'RAD 101 A', 'type' => MaintenanceType::OilChange, 'description' => 'Scheduled oil and filter change.', 'date' => '2026-08-20', 'cost' => 45000, 'status' => MaintenanceStatus::Completed],
            ['plate' => 'RAD 202 A', 'type' => MaintenanceType::Tyres, 'description' => 'Replaced two rear tyres.', 'date' => '2026-08-10', 'cost' => 260000, 'status' => MaintenanceStatus::Completed],
            ['plate' => 'RAD 301 A', 'type' => MaintenanceType::Service, 'description' => 'Full service — 80,000 km interval.', 'date' => '2026-07-30', 'cost' => 220000, 'status' => MaintenanceStatus::Completed],
            ['plate' => 'RAD 203 A', 'type' => MaintenanceType::Inspection, 'description' => 'Annual roadworthiness inspection.', 'date' => '2026-09-01', 'cost' => 20000, 'status' => MaintenanceStatus::Completed],
            ['plate' => 'RAD 401 A', 'type' => MaintenanceType::Service, 'description' => 'Upcoming scheduled service.', 'date' => '2026-10-15', 'cost' => 150000, 'status' => MaintenanceStatus::Scheduled],
        ];

        foreach ($rows as $row) {
            $vehicle = Vehicle::query()->where('plate_number', $row['plate'])->first();

            if (! $vehicle) {
                continue;
            }

            VehicleMaintenance::create([
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => $row['type'],
                'description' => $row['description'],
                'date' => $row['date'],
                'cost' => $row['cost'],
                'mileage' => $vehicle->mileage,
                'status' => $row['status'],
            ]);
        }
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, CorporateCustomer>  $corporates
     */
    private function seedBookings(array $customers, array $corporates): void
    {
        $stations = Station::query()->pluck('id', 'code');
        $categories = VehicleCategory::query()->pluck('id', 'name');
        $vehicles = Vehicle::query()->get()->keyBy('plate_number');
        $drivers = Driver::query()->get()->keyBy('license_number');
        $extraIds = Extra::query()->pluck('id', 'name');

        $kgl = $stations['KGL'];
        $kcy = $stations['KCY'];
        $rbv = $stations['RBV'];

        $customersByName = $customers;
        $corporatesByName = $corporates;

        // ---- Completed bookings (fully driven through create -> confirm -> pickup -> return) ----

        $this->driveCompletedBooking([
            'customer_id' => $customersByName['Emmanuel Bizimana']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-09-05 09:00:00', 'return_at' => '2026-09-08 09:00:00',
            'vehicle_category_id' => $categories['Economy'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2001', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 101 A'], null, [$extraIds['GPS navigator'] => 1], ['mileage' => 28450, 'fuel_level' => FuelLevel::Full], ['mileage' => 28900, 'fuel_level' => FuelLevel::Half, 'damage_charges' => 0], false);

        $this->driveCompletedBooking([
            'customer_id' => $customersByName['Solange Uwamahoro']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-09-10 11:00:00', 'return_at' => '2026-09-13 11:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::Chauffeur, 'payment_option' => PaymentOption::PayAtPickup,
        ], $vehicles['RAD 102 A'], $drivers['RW-DL-1001'], [$extraIds['Child seat'] => 1, $extraIds['Wi-Fi hotspot'] => 1], ['mileage' => 19300, 'fuel_level' => FuelLevel::Full], ['mileage' => 19640, 'fuel_level' => FuelLevel::Quarter, 'damage_charges' => 0], false);

        $this->driveCompletedBooking([
            'corporate_customer_id' => $corporatesByName['Kigali Heights Hotel']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-09-12 08:00:00', 'return_at' => '2026-09-15 08:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::Chauffeur, 'payment_option' => PaymentOption::CorporateBilling,
        ], $vehicles['RAD 201 A'], $drivers['RW-DL-1002'], [], ['mileage' => 11250, 'fuel_level' => FuelLevel::Full], ['mileage' => 11700, 'fuel_level' => FuelLevel::Half, 'damage_charges' => 0], false);

        $this->driveCompletedBooking([
            'customer_id' => $customersByName['Fabrice Rugamba']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-09-14 07:00:00', 'return_at' => '2026-09-16 19:00:00',
            'vehicle_category_id' => $categories['Pickup'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2005', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 401 A'], null, [], ['mileage' => 15650, 'fuel_level' => FuelLevel::Full], ['mileage' => 16050, 'fuel_level' => FuelLevel::Empty, 'damage_charges' => 25000], false);

        // Late return + damage — exercises outstanding_balance / extra_days_charge.
        $this->driveCompletedBooking([
            'corporate_customer_id' => $corporatesByName['Rwanda Tech Solutions Ltd']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-09-18 09:00:00', 'return_at' => '2026-09-20 09:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::CorporateBilling,
            'driver_license_number' => 'CORP-DL-001', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 104 A'], null, [$extraIds['Full cover'] => 1], ['mileage' => 14350, 'fuel_level' => FuelLevel::Full], ['mileage' => 14780, 'fuel_level' => FuelLevel::Half, 'damage_charges' => 60000], true);

        $this->driveCompletedBooking([
            'customer_id' => $customersByName['Grace Umutoni']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-09-22 10:00:00', 'return_at' => '2026-09-24 10:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2008', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 202 A'], null, [$extraIds['Roof rack'] => 1], ['mileage' => 46850, 'fuel_level' => FuelLevel::ThreeQuarter], ['mileage' => 47200, 'fuel_level' => FuelLevel::Quarter, 'damage_charges' => 0], false);

        // ---- Active rentals (picked up, not yet returned) ----

        $this->driveActiveRental([
            'customer_id' => $customersByName['Marie Claire Ingabire']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-09-29 09:00:00', 'return_at' => '2026-10-02 09:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2004', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 103 A'], null, [], ['mileage' => 8750, 'fuel_level' => FuelLevel::Full]);

        $this->driveActiveRental([
            'corporate_customer_id' => $corporatesByName['Inyange Logistics Ltd']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-09-28 08:00:00', 'return_at' => '2026-10-03 08:00:00',
            'vehicle_category_id' => $categories['Van'],
            'rental_type' => RentalType::Chauffeur, 'payment_option' => PaymentOption::CorporateBilling,
        ], $vehicles['RAD 301 A'], $drivers['RW-DL-1003'], [], ['mileage' => 81300, 'fuel_level' => FuelLevel::Full]);

        $this->driveActiveRental([
            'customer_id' => $customersByName['Emmanuel Bizimana']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-09-30 07:00:00', 'return_at' => '2026-10-01 19:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayAtPickup,
            'driver_license_number' => 'RW-DL-2001', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 203 A'], null, [$extraIds['GPS navigator'] => 1], ['mileage' => 17500, 'fuel_level' => FuelLevel::Full]);

        // ---- Ready for pickup (assigned, awaiting handover) ----

        $this->driveReadyForPickup([
            'customer_id' => $customersByName['Solange Uwamahoro']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-10-02 10:00:00', 'return_at' => '2026-10-05 10:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2002', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 102 A'], null);

        $this->driveReadyForPickup([
            'corporate_customer_id' => $corporatesByName['Kigali Heights Hotel']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-10-03 09:00:00', 'return_at' => '2026-10-06 09:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::Chauffeur, 'payment_option' => PaymentOption::CorporateBilling,
        ], $vehicles['RAD 201 A'], $drivers['RW-DL-1002']);

        $this->driveReadyForPickup([
            'customer_id' => $customersByName['Fabrice Rugamba']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-10-04 08:00:00', 'return_at' => '2026-10-06 08:00:00',
            'vehicle_category_id' => $categories['Pickup'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayAtPickup,
            'driver_license_number' => 'RW-DL-2005', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 401 A'], null);

        // ---- Confirmed (no vehicle assigned yet, or assigned but not readied) ----

        $this->driveConfirmed([
            'customer_id' => $customersByName['Marie Claire Ingabire']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-10-05 09:00:00', 'return_at' => '2026-10-07 09:00:00',
            'vehicle_category_id' => $categories['Economy'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2004', 'driver_license_category' => LicenceCategory::B,
        ], null, null);

        $this->driveConfirmed([
            'corporate_customer_id' => $corporatesByName['Rwanda Tech Solutions Ltd']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-10-06 10:00:00', 'return_at' => '2026-10-09 10:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::CorporateBilling,
            'driver_license_number' => 'CORP-DL-002', 'driver_license_category' => LicenceCategory::B,
        ], $vehicles['RAD 104 A'], null);

        $this->driveConfirmed([
            'customer_id' => $customersByName['Grace Umutoni']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-10-07 09:00:00', 'return_at' => '2026-10-10 09:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2008', 'driver_license_category' => LicenceCategory::B,
        ], null, null);

        // ---- Pending (awaiting staff confirmation) ----

        $this->drivePending([
            'customer_id' => $customersByName['Innocent Nshimiyimana']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-10-08 09:00:00', 'return_at' => '2026-10-10 09:00:00',
            'vehicle_category_id' => $categories['Economy'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2003', 'driver_license_category' => LicenceCategory::B,
        ]);

        $this->drivePending([
            'corporate_customer_id' => $corporatesByName['Inyange Logistics Ltd']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-10-09 08:00:00', 'return_at' => '2026-10-12 08:00:00',
            'vehicle_category_id' => $categories['Van'],
            'rental_type' => RentalType::Chauffeur, 'payment_option' => PaymentOption::CorporateBilling,
        ]);

        $this->drivePending([
            'customer_id' => $customersByName['Grace Umutoni']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-10-10 10:00:00', 'return_at' => '2026-10-12 10:00:00',
            'vehicle_category_id' => $categories['Pickup'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayAtPickup,
            'driver_license_number' => 'RW-DL-2008', 'driver_license_category' => LicenceCategory::B,
        ]);

        // ---- Cancelled ----

        $this->driveCancelled([
            'customer_id' => $customersByName['Yvette Mukashyaka']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-10-11 09:00:00', 'return_at' => '2026-10-13 09:00:00',
            'vehicle_category_id' => $categories['Sedan'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2006', 'driver_license_category' => LicenceCategory::B,
        ], 'Customer changed travel dates.');

        $this->driveCancelled([
            'customer_id' => $customersByName['Fabrice Rugamba']->id,
            'pickup_station_id' => $kgl, 'return_station_id' => $kgl,
            'pickup_at' => '2026-10-12 09:00:00', 'return_at' => '2026-10-14 09:00:00',
            'vehicle_category_id' => $categories['SUV'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayNow,
            'driver_license_number' => 'RW-DL-2005', 'driver_license_category' => LicenceCategory::B,
        ], 'Duplicate booking made in error.');

        // ---- No-show ----

        $this->driveNoShow([
            'customer_id' => $customersByName['Olivier Ndayisenga']->id,
            'pickup_station_id' => $kcy, 'return_station_id' => $kcy,
            'pickup_at' => '2026-10-08 09:00:00', 'return_at' => '2026-10-10 09:00:00',
            'vehicle_category_id' => $categories['Economy'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayAtPickup,
            'driver_license_number' => 'RW-DL-2007', 'driver_license_category' => LicenceCategory::B,
        ]);

        $this->driveNoShow([
            'customer_id' => $customersByName['Innocent Nshimiyimana']->id,
            'pickup_station_id' => $rbv, 'return_station_id' => $rbv,
            'pickup_at' => '2026-10-09 09:00:00', 'return_at' => '2026-10-11 09:00:00',
            'vehicle_category_id' => $categories['Pickup'],
            'rental_type' => RentalType::SelfDrive, 'payment_option' => PaymentOption::PayAtPickup,
            'driver_license_number' => 'RW-DL-2003', 'driver_license_category' => LicenceCategory::B,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $extras
     * @param  array<string, mixed>  $pickup
     * @param  array<string, mixed>  $return
     */
    private function driveCompletedBooking(array $data, Vehicle $vehicle, ?Driver $driver, array $extras, array $pickup, array $return, bool $lateReturn): void
    {
        $pickupAt = Carbon::parse($data['pickup_at']);
        $returnAt = Carbon::parse($data['return_at']);

        Carbon::setTestNow($pickupAt->copy()->subHours(3));
        $data['assigned_vehicle_id'] = $vehicle->id;
        $booking = $this->create($data, $extras);
        $this->confirm($booking);

        if ($driver) {
            $this->action('assignDriver', $booking, $driver);
        }

        $this->action('markReadyForPickup', $booking);

        Carbon::setTestNow($pickupAt);
        $this->rental('recordPickup', $booking, $pickup);

        Carbon::setTestNow($lateReturn ? $returnAt->copy()->addDay() : $returnAt);
        $this->rental('recordReturn', $booking, $return);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $extras
     * @param  array<string, mixed>  $pickup
     */
    private function driveActiveRental(array $data, Vehicle $vehicle, ?Driver $driver, array $extras, array $pickup): void
    {
        $pickupAt = Carbon::parse($data['pickup_at']);

        Carbon::setTestNow($pickupAt->copy()->subHours(3));
        $data['assigned_vehicle_id'] = $vehicle->id;
        $booking = $this->create($data, $extras);
        $this->confirm($booking);

        if ($driver) {
            $this->action('assignDriver', $booking, $driver);
        }

        $this->action('markReadyForPickup', $booking);

        Carbon::setTestNow($pickupAt);
        $this->rental('recordPickup', $booking, $pickup);
    }

    private function driveReadyForPickup(array $data, ?Vehicle $vehicle, ?Driver $driver): void
    {
        $booking = $this->create($data, []);
        $this->confirm($booking);

        $vehicle ??= Vehicle::query()->where('vehicle_category_id', $data['vehicle_category_id'])
            ->where('status', \App\Enums\VehicleStatus::Available)->first();

        if ($vehicle) {
            $this->action('assignVehicle', $booking, $vehicle);
        }

        if ($driver) {
            $this->action('assignDriver', $booking, $driver);
        }

        $this->action('markReadyForPickup', $booking);
    }

    private function driveConfirmed(array $data, ?Vehicle $vehicle, ?Driver $driver): void
    {
        $booking = $this->create($data, []);
        $this->confirm($booking);

        if ($vehicle) {
            $this->action('assignVehicle', $booking, $vehicle);
        }

        if ($driver) {
            $this->action('assignDriver', $booking, $driver);
        }
    }

    private function drivePending(array $data): void
    {
        $this->create($data, []);
    }

    private function driveCancelled(array $data, string $reason): void
    {
        $booking = $this->create($data, []);
        $this->confirm($booking);

        $result = $this->bookingService->cancel($booking, $reason);

        if (! $result['status']) {
            throw new RuntimeException('Cancel failed: '.$result['message']);
        }
    }

    private function driveNoShow(array $data): void
    {
        $booking = $this->create($data, []);
        $this->confirm($booking);

        $result = $this->bookingService->markNoShow($booking);

        if (! $result['status']) {
            throw new RuntimeException('No-show failed: '.$result['message']);
        }
    }

    private function create(array $data, array $extras): Booking
    {
        $result = $this->bookingService->create($data, $extras);

        if (! $result['status']) {
            throw new RuntimeException('Booking create failed: '.$result['message']);
        }

        return $result['data'];
    }

    private function confirm(Booking $booking): void
    {
        $result = $this->bookingService->confirm($booking);

        if (! $result['status']) {
            throw new RuntimeException('Booking confirm failed: '.$result['message']);
        }
    }

    private function action(string $method, Booking $booking, mixed ...$args): void
    {
        $result = $this->bookingService->{$method}($booking, ...$args);

        if (! $result['status']) {
            throw new RuntimeException("Booking {$method} failed: ".$result['message']);
        }
    }

    private function rental(string $method, Booking $booking, array $data): void
    {
        $result = $this->rentalService->{$method}($booking, $data);

        if (! $result['status']) {
            throw new RuntimeException("Rental {$method} failed: ".$result['message']);
        }
    }
}
