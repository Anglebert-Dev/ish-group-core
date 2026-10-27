<?php

use App\Http\Controllers\Web\ActivityLog\ActivityLogController;
use App\Http\Controllers\Web\Auth\AuthController;
use App\Http\Controllers\Web\Auth\CustomerAuthController;
use App\Http\Controllers\Web\Auth\CustomerProfileController;
use App\Http\Controllers\Web\Auth\ProfileController;
use App\Http\Controllers\Web\Booking\BookingController;
use App\Http\Controllers\Web\Booking\CustomerBookingController;
use App\Http\Controllers\Web\Chauffeur\ChauffeurJobController;
use App\Http\Controllers\Web\CorporateCustomer\CorporateCustomerController;
use App\Http\Controllers\Web\Customer\CustomerController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\AccountAssignment\AccountAssignmentController;
use App\Http\Controllers\Web\ChartOfAccount\ChartOfAccountController;
use App\Http\Controllers\Web\Driver\DriverController;
use App\Http\Controllers\Web\Expense\ExpenseController;
use App\Http\Controllers\Web\Extra\ExtraController;
use App\Http\Controllers\Web\Finance\FinanceHomeController;
use App\Http\Controllers\Web\FinancialStatement\FinancialStatementController;
use App\Http\Controllers\Web\GeneralLedger\GeneralLedgerController;
use App\Http\Controllers\Web\Invoice\InvoiceController;
use App\Http\Controllers\Web\Notification\NotificationController;
use App\Http\Controllers\Web\Payment\PaymentController;
use App\Http\Controllers\Web\PublicHomeController;
use App\Http\Controllers\Web\Rental\RentalController;
use App\Http\Controllers\Web\Report\ReportController;
use App\Http\Controllers\Web\Role\RoleController;
use App\Http\Controllers\Web\Setting\SettingController;
use App\Http\Controllers\Web\Station\StationController;
use App\Http\Controllers\Web\Supplier\SupplierController;
use App\Http\Controllers\Web\User\UserController;
use App\Http\Controllers\Web\Vehicle\PublicVehicleController;
use App\Http\Controllers\Web\Vehicle\VehicleController;
use App\Http\Controllers\Web\VehicleCategory\VehicleCategoryController;
use App\Http\Controllers\Web\VehicleOwner\VehicleOwnerController;
use App\Http\Controllers\Web\FuelLog\FuelLogController;
use App\Http\Controllers\Web\VehicleMaintenance\VehicleMaintenanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicHomeController::class, 'index'])->name('home');
Route::view('/about', 'public.about')->name('about');
Route::view('/contact', 'public.contact')->name('contact');
Route::view('/services', 'public.services')->name('services');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/terms', 'public.terms')->name('terms');

Route::get('/vehicles', [PublicVehicleController::class, 'index'])->name('vehicles.index');
Route::get('/vehicles/{vehicle}', [PublicVehicleController::class, 'show'])->name('vehicles.show');

Route::get('/booking/search', [PublicVehicleController::class, 'search'])->name('booking.search');

Route::middleware(['auth:customer', 'customer.active'])->group(function () {
    Route::get('/booking/details', [CustomerBookingController::class, 'details'])->name('booking.details');
    Route::post('/booking/details', [CustomerBookingController::class, 'storeDetails'])->name('booking.details.store');
    Route::get('/booking/review', [CustomerBookingController::class, 'review'])->name('booking.review');
    Route::post('/booking/review', [CustomerBookingController::class, 'confirm'])->name('booking.review.confirm');
    Route::get('/booking/confirmation/{booking}', [CustomerBookingController::class, 'confirmation'])->name('booking.confirmation');
    Route::get('/booking/confirmation/{booking}/receipt', [CustomerBookingController::class, 'receipt'])->name('booking.receipt');
    Route::get('/booking/confirmation/{booking}/receipt.pdf', [CustomerBookingController::class, 'downloadReceipt'])->name('booking.receipt.pdf');
});

/*
|--------------------------------------------------------------------------
| Customer authentication (public booking portal — "customer" guard)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:customer')->group(function () {
    Route::get('/register', [CustomerAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
    Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('/forgot-password', [CustomerAuthController::class, 'showForgotPasswordForm'])->name('customer.password.request');
    Route::post('/forgot-password', [CustomerAuthController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset')
        ->name('customer.password.email');
    Route::get('/reset-password/{token}', [CustomerAuthController::class, 'showResetForm'])->name('customer.password.reset');
    Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('customer.password.update');
});
Route::post('/logout', [CustomerAuthController::class, 'logout'])
    ->middleware('auth:customer')
    ->name('logout');

Route::middleware(['auth:customer', 'auth.session', 'exclusive.portal:customer', 'customer.active', 'no-back-cache'])->group(function () {
    Route::get('/account', [CustomerProfileController::class, 'dashboard'])->name('profile');
    Route::get('/account/profile', [CustomerProfileController::class, 'show'])->name('account.profile');
    Route::put('/account/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::put('/account/profile/password', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/account/bookings', [CustomerBookingController::class, 'myBookings'])->name('account.bookings.index');
    Route::get('/account/bookings/{booking}', [CustomerBookingController::class, 'myBookingShow'])->name('account.bookings.show');
    Route::get('/account/bookings/{booking}/pay', [CustomerBookingController::class, 'pay'])->name('account.bookings.pay');
    Route::post('/account/bookings/{booking}/pay', [CustomerBookingController::class, 'confirmPay'])->name('account.bookings.pay.confirm');
    Route::post('/account/bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->name('account.bookings.cancel');
    Route::post('/account/bookings/{booking}/extend', [CustomerBookingController::class, 'extend'])->name('account.bookings.extend');
});

/*
|--------------------------------------------------------------------------
| Internal application (institution / rental company operations)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->group(function () {

    Route::get('/', function () {
        return auth('staff')->check()
            ? redirect()->route('dashboard')
            : redirect()->route('staff.login');
    })->name('staff.home');

    /*
    |----------------------------------------------------------------------
    | Staff authentication ("staff" guard)
    |----------------------------------------------------------------------
    */
    Route::middleware('guest:staff')->group(function () {
        Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('staff.register');
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('staff.register.store');
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('staff.login');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('staff.login.store');
        Route::get('/mfa/challenge', [AuthController::class, 'showMfaChallenge'])->name('staff.mfa.challenge');
        Route::post('/mfa/challenge', [AuthController::class, 'verifyMfa'])
            ->middleware('throttle:login')
            ->name('staff.mfa.verify');
        Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('staff.password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
            ->middleware('throttle:password-reset')
            ->name('staff.password.email');
        Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('staff.password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:password-reset')
            ->name('staff.password.update');
    });
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:staff')
        ->name('staff.logout');

});

Route::prefix('app')->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'approved', 'no-back-cache'])->group(function () {

    Route::get('/confirm-password', [AuthController::class, 'showConfirmPasswordForm'])->name('staff.password.confirm');
    Route::post('/confirm-password', [AuthController::class, 'confirmPassword'])->name('staff.password.confirm.store');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('staff.profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('staff.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('staff.profile.password');
    Route::post('/profile/mfa/setup', [ProfileController::class, 'beginMfaSetup'])
        ->middleware('password.confirm.staff')
        ->name('staff.profile.mfa.setup');
    Route::post('/profile/mfa/confirm', [ProfileController::class, 'confirmMfaSetup'])
        ->middleware('password.confirm.staff')
        ->name('staff.profile.mfa.confirm');
    Route::post('/profile/mfa/cancel', [ProfileController::class, 'cancelMfaSetup'])
        ->name('staff.profile.mfa.cancel');
    Route::post('/profile/mfa/disable', [ProfileController::class, 'disableMfa'])
        ->middleware('password.confirm.staff')
        ->name('staff.profile.mfa.disable');
    Route::post('/profile/mfa/recovery/regenerate', [ProfileController::class, 'regenerateRecoveryCodes'])
        ->middleware('password.confirm.staff')
        ->name('staff.profile.mfa.recovery.regenerate');

    // Operations
    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->middleware('permission:list bookings.booking')->name('index');
        Route::get('/export/pdf', [BookingController::class, 'exportPdf'])->middleware('permission:list bookings.booking')->name('export.pdf');
        Route::get('/export/excel', [BookingController::class, 'exportExcel'])->middleware('permission:list bookings.booking')->name('export.excel');
        Route::get('/create', [BookingController::class, 'create'])->middleware('permission:create bookings.booking')->name('create');
        Route::post('/check-availability', [BookingController::class, 'checkAvailability'])->middleware('permission:create bookings.booking')->name('check-availability');
        Route::post('/', [BookingController::class, 'store'])->middleware('permission:create bookings.booking')->name('store');
        Route::get('/{booking}', [BookingController::class, 'show'])->middleware('permission:read bookings.booking')->name('show');
        Route::post('/{booking}/confirm', [BookingController::class, 'confirm'])->middleware('permission:approve bookings.booking')->name('confirm');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('permission:update bookings.booking')->name('cancel');
        Route::post('/{booking}/no-show', [BookingController::class, 'markNoShow'])->middleware('permission:update bookings.booking')->name('no-show');
        Route::post('/{booking}/assign-vehicle', [BookingController::class, 'assignVehicle'])->middleware('permission:assign bookings.booking')->name('assign-vehicle');
        Route::post('/{booking}/assign-driver', [BookingController::class, 'assignDriver'])->middleware('permission:assign bookings.booking')->name('assign-driver');
        Route::post('/{booking}/ready-for-pickup', [BookingController::class, 'markReadyForPickup'])->middleware('permission:update bookings.booking')->name('ready-for-pickup');
        Route::post('/{booking}/extend', [BookingController::class, 'extend'])->middleware('permission:update bookings.booking')->name('extend');
    });

    Route::prefix('extras')->name('extras.')->group(function () {
        Route::get('/', [ExtraController::class, 'index'])->middleware('permission:list bookings.booking_extra')->name('index');
        Route::get('/export/pdf', [ExtraController::class, 'exportPdf'])->middleware('permission:list bookings.booking_extra')->name('export.pdf');
        Route::get('/export/excel', [ExtraController::class, 'exportExcel'])->middleware('permission:list bookings.booking_extra')->name('export.excel');
        Route::get('/create', [ExtraController::class, 'create'])->middleware('permission:create bookings.booking_extra')->name('create');
        Route::post('/', [ExtraController::class, 'store'])->middleware('permission:create bookings.booking_extra')->name('store');
        Route::get('/{extra}', [ExtraController::class, 'show'])->middleware('permission:read bookings.booking_extra')->name('show');
        Route::get('/{extra}/edit', [ExtraController::class, 'edit'])->middleware('permission:update bookings.booking_extra')->name('edit');
        Route::put('/{extra}', [ExtraController::class, 'update'])->middleware('permission:update bookings.booking_extra')->name('update');
        Route::put('/{extra}/status', [ExtraController::class, 'updateStatus'])->middleware('permission:update bookings.booking_extra')->name('status');
        Route::delete('/{extra}', [ExtraController::class, 'destroy'])->middleware('permission:delete bookings.booking_extra')->name('destroy');
    });

    Route::prefix('rentals')->name('rentals.')->group(function () {
        Route::get('/', [RentalController::class, 'index'])->middleware('permission:list operations.rental')->name('index');
        Route::get('/export/pdf', [RentalController::class, 'exportPdf'])->middleware('permission:list operations.rental')->name('export.pdf');
        Route::get('/export/excel', [RentalController::class, 'exportExcel'])->middleware('permission:list operations.rental')->name('export.excel');
        Route::get('/{booking}', [RentalController::class, 'show'])->middleware('permission:read operations.rental')->name('show');
        Route::post('/{booking}/pickup', [RentalController::class, 'recordPickup'])->middleware('permission:dispatch operations.rental')->name('pickup');
        Route::post('/{booking}/return', [RentalController::class, 'recordReturn'])->middleware('permission:update operations.rental')->name('return');
    });

    Route::prefix('chauffeur-jobs')->name('chauffeur.')->group(function () {
        Route::get('/', [ChauffeurJobController::class, 'index'])->middleware('permission:list drivers.chauffeur_job')->name('index');
        Route::get('/export/pdf', [ChauffeurJobController::class, 'exportPdf'])->middleware('permission:list drivers.chauffeur_job')->name('export.pdf');
        Route::get('/export/excel', [ChauffeurJobController::class, 'exportExcel'])->middleware('permission:list drivers.chauffeur_job')->name('export.excel');
        Route::get('/{booking}', [ChauffeurJobController::class, 'show'])->middleware('permission:read drivers.chauffeur_job')->name('show');
    });

    // Fleet
    Route::prefix('fleet/owners')->name('fleet.owners.')->group(function () {
        Route::get('/', [VehicleOwnerController::class, 'index'])->middleware('permission:list fleet.vehicle_owner')->name('index');
        Route::get('/export/pdf', [VehicleOwnerController::class, 'exportPdf'])->middleware('permission:list fleet.vehicle_owner')->name('export.pdf');
        Route::get('/export/excel', [VehicleOwnerController::class, 'exportExcel'])->middleware('permission:list fleet.vehicle_owner')->name('export.excel');
        Route::get('/create', [VehicleOwnerController::class, 'create'])->middleware('permission:create fleet.vehicle_owner')->name('create');
        Route::post('/', [VehicleOwnerController::class, 'store'])->middleware('permission:create fleet.vehicle_owner')->name('store');
        Route::get('/{owner}', [VehicleOwnerController::class, 'show'])->middleware('permission:read fleet.vehicle_owner')->name('show');
        Route::get('/{owner}/edit', [VehicleOwnerController::class, 'edit'])->middleware('permission:update fleet.vehicle_owner')->name('edit');
        Route::put('/{owner}', [VehicleOwnerController::class, 'update'])->middleware('permission:update fleet.vehicle_owner')->name('update');
        Route::put('/{owner}/status', [VehicleOwnerController::class, 'updateStatus'])->middleware('permission:update fleet.vehicle_owner')->name('status');
        Route::delete('/{owner}', [VehicleOwnerController::class, 'destroy'])->middleware('permission:delete fleet.vehicle_owner')->name('destroy');
    });

    Route::prefix('fleet/vehicles')->name('fleet.vehicles.')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->middleware('permission:list fleet.vehicle')->name('index');
        Route::get('/export/pdf', [VehicleController::class, 'exportPdf'])->middleware('permission:list fleet.vehicle')->name('export.pdf');
        Route::get('/export/excel', [VehicleController::class, 'exportExcel'])->middleware('permission:list fleet.vehicle')->name('export.excel');
        Route::get('/create', [VehicleController::class, 'create'])->middleware('permission:create fleet.vehicle')->name('create');
        Route::get('/catalog/models', [VehicleController::class, 'catalogModels'])->middleware('permission:create fleet.vehicle|update fleet.vehicle')->name('catalog.models');
        Route::post('/', [VehicleController::class, 'store'])->middleware('permission:create fleet.vehicle')->name('store');
        Route::get('/{vehicle}', [VehicleController::class, 'show'])->middleware('permission:read fleet.vehicle')->name('show');
        Route::get('/{vehicle}/edit', [VehicleController::class, 'edit'])->middleware('permission:update fleet.vehicle')->name('edit');
        Route::put('/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:update fleet.vehicle')->name('update');
        Route::put('/{vehicle}/status', [VehicleController::class, 'updateStatus'])->middleware('permission:update fleet.vehicle')->name('status');
        Route::put('/{vehicle}/compliance', [VehicleController::class, 'updateCompliance'])->middleware('permission:update fleet.vehicle')->name('compliance');
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:delete fleet.vehicle')->name('destroy');
        Route::post('/{vehicle}/images', [VehicleController::class, 'uploadImages'])->middleware('permission:upload fleet.vehicle')->name('images.store');
        Route::delete('/{vehicle}/images/{image}', [VehicleController::class, 'destroyImage'])->middleware('permission:delete fleet.vehicle')->name('images.destroy');
    });

    Route::prefix('fleet/categories')->name('fleet.categories.')->group(function () {
        Route::get('/', [VehicleCategoryController::class, 'index'])->middleware('permission:list fleet.vehicle_category')->name('index');
        Route::get('/export/pdf', [VehicleCategoryController::class, 'exportPdf'])->middleware('permission:list fleet.vehicle_category')->name('export.pdf');
        Route::get('/export/excel', [VehicleCategoryController::class, 'exportExcel'])->middleware('permission:list fleet.vehicle_category')->name('export.excel');
        Route::get('/create', [VehicleCategoryController::class, 'create'])->middleware('permission:create fleet.vehicle_category')->name('create');
        Route::post('/', [VehicleCategoryController::class, 'store'])->middleware('permission:create fleet.vehicle_category')->name('store');
        Route::get('/{category}', [VehicleCategoryController::class, 'show'])->middleware('permission:read fleet.vehicle_category')->name('show');
        Route::get('/{category}/edit', [VehicleCategoryController::class, 'edit'])->middleware('permission:update fleet.vehicle_category')->name('edit');
        Route::put('/{category}', [VehicleCategoryController::class, 'update'])->middleware('permission:update fleet.vehicle_category')->name('update');
        Route::put('/{category}/status', [VehicleCategoryController::class, 'updateStatus'])->middleware('permission:update fleet.vehicle_category')->name('status');
        Route::delete('/{category}', [VehicleCategoryController::class, 'destroy'])->middleware('permission:delete fleet.vehicle_category')->name('destroy');
    });

    Route::prefix('fleet/maintenance')->name('fleet.maintenance.')->group(function () {
        Route::get('/', [VehicleMaintenanceController::class, 'index'])->middleware('permission:list fleet.vehicle_maintenance')->name('index');
        Route::get('/export/pdf', [VehicleMaintenanceController::class, 'exportPdf'])->middleware('permission:list fleet.vehicle_maintenance')->name('export.pdf');
        Route::get('/export/excel', [VehicleMaintenanceController::class, 'exportExcel'])->middleware('permission:list fleet.vehicle_maintenance')->name('export.excel');
        Route::get('/create', [VehicleMaintenanceController::class, 'create'])->middleware('permission:create fleet.vehicle_maintenance')->name('create');
        Route::post('/', [VehicleMaintenanceController::class, 'store'])->middleware('permission:create fleet.vehicle_maintenance')->name('store');
        Route::get('/{maintenance}', [VehicleMaintenanceController::class, 'show'])->middleware('permission:read fleet.vehicle_maintenance')->name('show');
        Route::get('/{maintenance}/edit', [VehicleMaintenanceController::class, 'edit'])->middleware('permission:update fleet.vehicle_maintenance')->name('edit');
        Route::put('/{maintenance}', [VehicleMaintenanceController::class, 'update'])->middleware('permission:update fleet.vehicle_maintenance')->name('update');
        Route::put('/{maintenance}/status', [VehicleMaintenanceController::class, 'updateStatus'])->middleware('permission:update fleet.vehicle_maintenance')->name('status');
        Route::delete('/{maintenance}', [VehicleMaintenanceController::class, 'destroy'])->middleware('permission:delete fleet.vehicle_maintenance')->name('destroy');
    });

    Route::prefix('fleet/fuel-logs')->name('fleet.fuel_logs.')->group(function () {
        Route::get('/', [FuelLogController::class, 'index'])->middleware('permission:list fleet.fuel_log')->name('index');
        Route::get('/export/pdf', [FuelLogController::class, 'exportPdf'])->middleware('permission:list fleet.fuel_log')->name('export.pdf');
        Route::get('/export/excel', [FuelLogController::class, 'exportExcel'])->middleware('permission:list fleet.fuel_log')->name('export.excel');
        Route::get('/create', [FuelLogController::class, 'create'])->middleware('permission:create fleet.fuel_log')->name('create');
        Route::post('/', [FuelLogController::class, 'store'])->middleware('permission:create fleet.fuel_log')->name('store');
        Route::get('/{fuelLog}', [FuelLogController::class, 'show'])->middleware('permission:read fleet.fuel_log')->name('show');
        Route::get('/{fuelLog}/edit', [FuelLogController::class, 'edit'])->middleware('permission:update fleet.fuel_log')->name('edit');
        Route::put('/{fuelLog}', [FuelLogController::class, 'update'])->middleware('permission:update fleet.fuel_log')->name('update');
        Route::put('/{fuelLog}/status', [FuelLogController::class, 'updateStatus'])->middleware('permission:update fleet.fuel_log')->name('status');
        Route::delete('/{fuelLog}', [FuelLogController::class, 'destroy'])->middleware('permission:delete fleet.fuel_log')->name('destroy');
    });

    Route::view('/inspections', 'app.inspections.index')->name('inspections.index');
    Route::view('/inspections/{id}', 'app.inspections.show')->name('inspections.show');

    // People
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:list customers.customer')->name('customers.index');
    Route::get('/customers/export/pdf', [CustomerController::class, 'exportPdf'])->middleware('permission:list customers.customer')->name('customers.export.pdf');
    Route::get('/customers/export/excel', [CustomerController::class, 'exportExcel'])->middleware('permission:list customers.customer')->name('customers.export.excel');
    Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('permission:create customers.customer')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:create customers.customer')->name('customers.store');

   
    // Corporate customers are managed entirely inside the app by staff.
    Route::prefix('customers/corporate')->name('customers.corporate.')->group(function () {
        Route::get('/', [CorporateCustomerController::class, 'index'])->middleware('permission:list customers.corporate_customer')->name('index');
        Route::get('/export/pdf', [CorporateCustomerController::class, 'exportPdf'])->middleware('permission:list customers.corporate_customer')->name('export.pdf');
        Route::get('/export/excel', [CorporateCustomerController::class, 'exportExcel'])->middleware('permission:list customers.corporate_customer')->name('export.excel');
        Route::get('/create', [CorporateCustomerController::class, 'create'])->middleware('permission:create customers.corporate_customer')->name('create');
        Route::post('/', [CorporateCustomerController::class, 'store'])->middleware('permission:create customers.corporate_customer')->name('store');
        Route::get('/{corporateCustomer}', [CorporateCustomerController::class, 'show'])->middleware('permission:read customers.corporate_customer')->name('show');
        Route::get('/{corporateCustomer}/edit', [CorporateCustomerController::class, 'edit'])->middleware('permission:update customers.corporate_customer')->name('edit');
        Route::put('/{corporateCustomer}', [CorporateCustomerController::class, 'update'])->middleware('permission:update customers.corporate_customer')->name('update');
        Route::put('/{corporateCustomer}/status', [CorporateCustomerController::class, 'updateStatus'])->middleware('permission:update customers.corporate_customer')->name('status');
        Route::delete('/{corporateCustomer}', [CorporateCustomerController::class, 'destroy'])->middleware('permission:delete customers.corporate_customer')->name('destroy');
    });

    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:read customers.customer')->name('customers.show');
    Route::put('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('permission:update customers.customer')->name('customers.status');
    Route::post('/customers/{customer}/portal-account', [CustomerController::class, 'generatePortalAccount'])->middleware('permission:update customers.customer')->name('customers.portal-account');
    Route::post('/customers/{customer}/reset-password', [CustomerController::class, 'resetPortalPassword'])->middleware('permission:update customers.customer')->name('customers.reset-password');

    Route::prefix('drivers')->name('drivers.')->group(function () {
        Route::get('/', [DriverController::class, 'index'])->middleware('permission:list drivers.driver')->name('index');
        Route::get('/export/pdf', [DriverController::class, 'exportPdf'])->middleware('permission:list drivers.driver')->name('export.pdf');
        Route::get('/export/excel', [DriverController::class, 'exportExcel'])->middleware('permission:list drivers.driver')->name('export.excel');
        Route::get('/create', [DriverController::class, 'create'])->middleware('permission:create drivers.driver')->name('create');
        Route::post('/', [DriverController::class, 'store'])->middleware('permission:create drivers.driver')->name('store');
        Route::get('/{driver}', [DriverController::class, 'show'])->middleware('permission:read drivers.driver')->name('show');
        Route::get('/{driver}/edit', [DriverController::class, 'edit'])->middleware('permission:update drivers.driver')->name('edit');
        Route::put('/{driver}', [DriverController::class, 'update'])->middleware('permission:update drivers.driver')->name('update');
        Route::put('/{driver}/status', [DriverController::class, 'updateStatus'])->middleware('permission:update drivers.driver')->name('status');
        Route::put('/{driver}/license', [DriverController::class, 'updateLicense'])->middleware('permission:update drivers.driver')->name('license');
        Route::delete('/{driver}', [DriverController::class, 'destroy'])->middleware('permission:delete drivers.driver')->name('destroy');
        Route::post('/{driver}/documents', [DriverController::class, 'uploadDocument'])->middleware('permission:upload drivers.driver')->name('documents.store');
        Route::get('/{driver}/documents/{document}/download', [DriverController::class, 'downloadDocument'])->middleware('permission:read drivers.driver')->name('documents.download');
        Route::delete('/{driver}/documents/{document}', [DriverController::class, 'destroyDocument'])->middleware('permission:delete drivers.driver')->name('documents.destroy');
    });

    // Finance
    Route::get('/finance', [FinanceHomeController::class, 'index'])->name('finance.index');

    Route::prefix('finance/invoices')->name('finance.invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:list finance.invoice')->name('index');
        Route::get('/export/pdf', [InvoiceController::class, 'exportPdf'])->middleware('permission:list finance.invoice')->name('export.pdf');
        Route::get('/export/excel', [InvoiceController::class, 'exportExcel'])->middleware('permission:list finance.invoice')->name('export.excel');
        Route::get('/create', [InvoiceController::class, 'create'])->middleware('permission:create finance.invoice')->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:create finance.invoice')->name('store');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:read finance.invoice')->name('show');
        Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('permission:update finance.invoice')->name('edit');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:update finance.invoice')->name('update');
        Route::post('/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('permission:update finance.invoice')->name('issue');
        Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:update finance.invoice')->name('cancel');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:delete finance.invoice')->name('destroy');
    });

    Route::prefix('finance/payments')->name('finance.payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->middleware('permission:list finance.payment')->name('index');
        Route::get('/export/pdf', [PaymentController::class, 'exportPdf'])->middleware('permission:list finance.payment')->name('export.pdf');
        Route::get('/export/excel', [PaymentController::class, 'exportExcel'])->middleware('permission:list finance.payment')->name('export.excel');
        Route::get('/create', [PaymentController::class, 'create'])->middleware('permission:create finance.payment')->name('create');
        Route::post('/', [PaymentController::class, 'store'])->middleware('permission:create finance.payment')->name('store');
        Route::get('/{payment}', [PaymentController::class, 'show'])->middleware('permission:read finance.payment')->name('show');
        Route::delete('/{payment}', [PaymentController::class, 'destroy'])->middleware(['permission:delete finance.payment', 'password.confirm.staff'])->name('destroy');
    });

    Route::view('/finance/deposits', 'app.finance.deposits.index')->name('finance.deposits.index');

    Route::prefix('finance/expenses')->name('finance.expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:list finance.expense')->name('index');
        Route::get('/export/pdf', [ExpenseController::class, 'exportPdf'])->middleware('permission:list finance.expense')->name('export.pdf');
        Route::get('/export/excel', [ExpenseController::class, 'exportExcel'])->middleware('permission:list finance.expense')->name('export.excel');
        Route::get('/create', [ExpenseController::class, 'create'])->middleware('permission:create finance.expense')->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:create finance.expense')->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:read finance.expense')->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('permission:update finance.expense')->name('edit');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:update finance.expense')->name('update');
        Route::post('/{expense}/post', [ExpenseController::class, 'post'])->middleware('permission:update finance.expense')->name('post');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:delete finance.expense')->name('destroy');
    });

    Route::prefix('finance/suppliers')->name('finance.suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->middleware('permission:list finance.supplier')->name('index');
        Route::get('/export/pdf', [SupplierController::class, 'exportPdf'])->middleware('permission:list finance.supplier')->name('export.pdf');
        Route::get('/export/excel', [SupplierController::class, 'exportExcel'])->middleware('permission:list finance.supplier')->name('export.excel');
        Route::get('/create', [SupplierController::class, 'create'])->middleware('permission:create finance.supplier')->name('create');
        Route::post('/', [SupplierController::class, 'store'])->middleware('permission:create finance.supplier')->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('permission:read finance.supplier')->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:update finance.supplier')->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:update finance.supplier')->name('update');
        Route::put('/{supplier}/status', [SupplierController::class, 'updateStatus'])->middleware('permission:update finance.supplier')->name('status');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:delete finance.supplier')->name('destroy');
    });

    Route::prefix('finance/chart-of-accounts')->name('finance.chart-of-accounts.')->group(function () {
        Route::get('/', [ChartOfAccountController::class, 'index'])->middleware('permission:list finance.chart_of_account')->name('index');
        Route::get('/export/pdf', [ChartOfAccountController::class, 'exportPdf'])->middleware('permission:list finance.chart_of_account')->name('export.pdf');
        Route::get('/export/excel', [ChartOfAccountController::class, 'exportExcel'])->middleware('permission:list finance.chart_of_account')->name('export.excel');
        Route::get('/create', [ChartOfAccountController::class, 'create'])->middleware('permission:create finance.chart_of_account')->name('create');
        Route::post('/', [ChartOfAccountController::class, 'store'])->middleware('permission:create finance.chart_of_account')->name('store');
        Route::get('/{chartOfAccount}', [ChartOfAccountController::class, 'show'])->middleware('permission:read finance.chart_of_account')->name('show');
        Route::get('/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])->middleware('permission:update finance.chart_of_account')->name('edit');
        Route::put('/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->middleware('permission:update finance.chart_of_account')->name('update');
        Route::put('/{chartOfAccount}/status', [ChartOfAccountController::class, 'updateStatus'])->middleware('permission:update finance.chart_of_account')->name('status');
        Route::delete('/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->middleware('permission:delete finance.chart_of_account')->name('destroy');
    });

    Route::prefix('finance/account-assignments')->name('finance.account-assignments.')->group(function () {
        Route::get('/', [AccountAssignmentController::class, 'index'])->middleware('permission:list finance.chart_of_account')->name('index');
        Route::get('/export/pdf', [AccountAssignmentController::class, 'exportPdf'])->middleware('permission:list finance.chart_of_account')->name('export.pdf');
        Route::get('/export/excel', [AccountAssignmentController::class, 'exportExcel'])->middleware('permission:list finance.chart_of_account')->name('export.excel');
        Route::put('/{accountAssignment}', [AccountAssignmentController::class, 'update'])->middleware('permission:update finance.chart_of_account')->name('update');
    });

    Route::prefix('finance/ledger')->name('finance.ledger.')->group(function () {
        Route::get('/', [GeneralLedgerController::class, 'index'])->middleware('permission:list finance.general_ledger')->name('index');
        Route::get('/export/pdf', [GeneralLedgerController::class, 'exportPdf'])->middleware('permission:list finance.general_ledger')->name('export.pdf');
        Route::get('/export/excel', [GeneralLedgerController::class, 'exportExcel'])->middleware('permission:list finance.general_ledger')->name('export.excel');
        Route::get('/{generalLedger}', [GeneralLedgerController::class, 'show'])->middleware('permission:read finance.general_ledger')->name('show');
    });

    Route::prefix('finance/statements')->name('finance.statements.')->group(function () {
        Route::get('/', [FinancialStatementController::class, 'index'])->middleware('permission:list finance.financial_statement')->name('index');
        Route::get('/trial-balance', [FinancialStatementController::class, 'trialBalance'])->middleware('permission:list finance.financial_statement')->name('trial-balance');
        Route::get('/profit-loss', [FinancialStatementController::class, 'profitLoss'])->middleware('permission:list finance.financial_statement')->name('profit-loss');
        Route::get('/balance-sheet', [FinancialStatementController::class, 'balanceSheet'])->middleware('permission:list finance.financial_statement')->name('balance-sheet');
    });

    // Management
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:list users.user')->name('users.index');
    Route::get('/users/export/pdf', [UserController::class, 'exportPdf'])->middleware('permission:list users.user')->name('users.export.pdf');
    Route::get('/users/export/excel', [UserController::class, 'exportExcel'])->middleware('permission:list users.user')->name('users.export.excel');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:create users.user')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware(['permission:create users.user', 'password.confirm.staff'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:read users.user')->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:update users.user')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware(['permission:update users.user', 'password.confirm.staff'])->name('users.update');
    Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware(['permission:update users.user', 'password.confirm.staff'])->name('users.status');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:list users.role')->name('roles.index');
    Route::get('/roles/export/pdf', [RoleController::class, 'exportPdf'])->middleware('permission:list users.role')->name('roles.export.pdf');
    Route::get('/roles/export/excel', [RoleController::class, 'exportExcel'])->middleware('permission:list users.role')->name('roles.export.excel');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:create users.role')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware(['permission:create users.role', 'password.confirm.staff'])->name('roles.store');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:read users.role')->name('roles.show');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:update users.role')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware(['permission:update users.role', 'password.confirm.staff'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware(['permission:delete users.role', 'password.confirm.staff'])->name('roles.destroy');

    Route::prefix('stations')->name('stations.')->group(function () {
        Route::get('/', [StationController::class, 'index'])->middleware('permission:list stations.station')->name('index');
        Route::get('/export/pdf', [StationController::class, 'exportPdf'])->middleware('permission:list stations.station')->name('export.pdf');
        Route::get('/export/excel', [StationController::class, 'exportExcel'])->middleware('permission:list stations.station')->name('export.excel');
        Route::get('/create', [StationController::class, 'create'])->middleware('permission:create stations.station')->name('create');
        Route::post('/', [StationController::class, 'store'])->middleware('permission:create stations.station')->name('store');
        Route::get('/{station}', [StationController::class, 'show'])->middleware('permission:read stations.station')->name('show');
        Route::get('/{station}/edit', [StationController::class, 'edit'])->middleware('permission:update stations.station')->name('edit');
        Route::put('/{station}', [StationController::class, 'update'])->middleware('permission:update stations.station')->name('update');
        Route::put('/{station}/status', [StationController::class, 'updateStatus'])->middleware('permission:update stations.station')->name('status');
        Route::delete('/{station}', [StationController::class, 'destroy'])->middleware('permission:delete stations.station')->name('destroy');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])
            ->middleware('permission:list reports.report')->name('index');

        Route::get('/export/pdf/{report}', [ReportController::class, 'exportPdf'])
            ->middleware('permission:list reports.report')->name('export.pdf');
        Route::get('/export/excel/{report}', [ReportController::class, 'exportExcel'])
            ->middleware('permission:list reports.report')->name('export.excel');

        Route::get('/bookings-summary', [ReportController::class, 'bookingsSummary'])
            ->middleware('permission:list reports.report')->name('bookings-summary');
        Route::get('/fleet-performance', [ReportController::class, 'fleetPerformance'])
            ->middleware('permission:list reports.report')->name('fleet-performance');
        Route::get('/driver-performance', [ReportController::class, 'driverPerformance'])
            ->middleware('permission:list reports.report')->name('driver-performance');
        Route::get('/vehicle-owner-revenue', [ReportController::class, 'vehicleOwnerRevenue'])
            ->middleware('permission:list reports.report')->name('vehicle-owner-revenue');
        Route::get('/compliance-status', [ReportController::class, 'complianceStatus'])
            ->middleware('permission:list reports.report')->name('compliance-status');
        Route::get('/customer-activity', [ReportController::class, 'customerActivity'])
            ->middleware('permission:list reports.report')->name('customer-activity');
    });

    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:list settings.setting')->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:update settings.setting')->name('settings.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->middleware('permission:list notifications.notification')->name('index');
        Route::get('/export/pdf', [NotificationController::class, 'exportPdf'])->middleware('permission:list notifications.notification')->name('export.pdf');
        Route::get('/export/excel', [NotificationController::class, 'exportExcel'])->middleware('permission:list notifications.notification')->name('export.excel');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('permission:update notifications.notification')->name('read-all');
        Route::get('/{notification}', [NotificationController::class, 'show'])->middleware('permission:read notifications.notification')->name('show');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('permission:update notifications.notification')->name('read');
    });

    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->middleware('permission:list activity.activity_log')->name('index');
        Route::get('/export/pdf', [ActivityLogController::class, 'exportPdf'])->middleware('permission:list activity.activity_log')->name('export.pdf');
        Route::get('/export/excel', [ActivityLogController::class, 'exportExcel'])->middleware('permission:list activity.activity_log')->name('export.excel');
        Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->middleware('permission:read activity.activity_log')->name('show');
    });
});
