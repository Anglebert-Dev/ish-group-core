<?php

use App\Http\Controllers\Web\ActivityLog\ActivityLogController;
use App\Http\Controllers\Web\Auth\AuthController;
use App\Http\Controllers\Web\Auth\ProfileController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\Landing\LandingController;
use App\Http\Controllers\Web\Notification\NotificationController;
use App\Http\Controllers\Web\Role\RoleController;
use App\Http\Controllers\Web\Setting\SettingController;
use App\Http\Controllers\Web\SuperAdmin\BusinessController;
use App\Http\Controllers\Web\SuperAdmin\CommunicatorController;
use App\Http\Controllers\Web\SuperAdmin\ImpersonationController;
use App\Http\Controllers\Web\SuperAdmin\ModuleController;
use App\Http\Controllers\Web\SuperAdmin\PlatformSettingController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Web\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing
|--------------------------------------------------------------------------
| Straight into the system, unless an active module (e.g. Frontend) offers a
| public landing page through its landing_page() hook.
*/
Route::get('/', LandingController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Staff application (/app, "staff" guard)
|--------------------------------------------------------------------------
| Modules register their own routes from Modules/<Name>/routes/web.php,
| normally with the same middleware stack as the authenticated group below
| plus 'module:<Name>'.
*/
Route::prefix('app')->group(function () {

    Route::get('/', function () {
        return auth('staff')->check()
            ? redirect()->route('dashboard')
            : redirect()->route('staff.login');
    })->name('staff.home');

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

Route::prefix('app')->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])->group(function () {

    Route::get('/confirm-password', [AuthController::class, 'showConfirmPasswordForm'])->name('staff.password.confirm');
    Route::post('/confirm-password', [AuthController::class, 'confirmPassword'])->name('staff.password.confirm.store');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('staff.profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('staff.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('not.impersonating')->name('staff.profile.password');
    Route::post('/profile/mfa/setup', [ProfileController::class, 'beginMfaSetup'])
        ->middleware(['not.impersonating', 'password.confirm.staff'])
        ->name('staff.profile.mfa.setup');
    Route::post('/profile/mfa/confirm', [ProfileController::class, 'confirmMfaSetup'])
        ->middleware(['not.impersonating', 'password.confirm.staff'])
        ->name('staff.profile.mfa.confirm');
    Route::post('/profile/mfa/cancel', [ProfileController::class, 'cancelMfaSetup'])
        ->name('staff.profile.mfa.cancel');
    Route::post('/profile/mfa/disable', [ProfileController::class, 'disableMfa'])
        ->middleware(['not.impersonating', 'password.confirm.staff'])
        ->name('staff.profile.mfa.disable');
    Route::post('/profile/mfa/recovery/regenerate', [ProfileController::class, 'regenerateRecoveryCodes'])
        ->middleware(['not.impersonating', 'password.confirm.staff'])
        ->name('staff.profile.mfa.recovery.regenerate');

    // Users & roles (scoped to the signed-in user's business)
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:list users.user')->name('users.index');
    Route::get('/users/export/pdf', [UserController::class, 'exportPdf'])->middleware('permission:list users.user')->name('users.export.pdf');
    Route::get('/users/export/excel', [UserController::class, 'exportExcel'])->middleware('permission:list users.user')->name('users.export.excel');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:create users.user')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware(['permission:create users.user', 'quota:users', 'password.confirm.staff'])->name('users.store');
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

    // Back to the superadmin who started impersonating (only them).
    Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])->name('impersonation.leave');

    /*
    |----------------------------------------------------------------------
    | Super admin (platform level — is_superadmin users only)
    |----------------------------------------------------------------------
    */
    Route::prefix('super-admin')->name('superadmin.')->middleware('superadmin')->group(function () {
        Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/export/pdf', [BusinessController::class, 'exportPdf'])->name('businesses.export.pdf');
        Route::get('/businesses/export/excel', [BusinessController::class, 'exportExcel'])->name('businesses.export.excel');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->middleware('password.confirm.staff')->name('businesses.store');
        Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::put('/businesses/{business}/active', [BusinessController::class, 'toggleActive'])->middleware('password.confirm.staff')->name('businesses.active');
        Route::put('/businesses/{business}/modules', [BusinessController::class, 'updateModules'])->name('businesses.modules');
        Route::delete('/businesses/{business}', [BusinessController::class, 'destroy'])->middleware('password.confirm.staff')->name('businesses.destroy');
        Route::put('/users/{user}/password', [BusinessController::class, 'resetUserPassword'])->middleware('password.confirm.staff')->name('users.password');
        Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->middleware(['not.impersonating', 'password.confirm.staff'])->name('impersonate');

        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('/modules/{module}/install', [ModuleController::class, 'install'])->middleware('password.confirm.staff')->name('modules.install');
        Route::post('/modules/{module}/update', [ModuleController::class, 'update'])->middleware('password.confirm.staff')->name('modules.update');
        Route::post('/modules/{module}/uninstall', [ModuleController::class, 'uninstall'])->middleware('password.confirm.staff')->name('modules.uninstall');
        Route::post('/modules/{module}/toggle', [ModuleController::class, 'toggle'])->middleware('password.confirm.staff')->name('modules.toggle');

        Route::get('/communicator', [CommunicatorController::class, 'index'])->name('communicator.index');
        Route::post('/communicator', [CommunicatorController::class, 'send'])->middleware('throttle:10,1')->name('communicator.send');

        Route::get('/settings', [PlatformSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [PlatformSettingController::class, 'update'])->name('settings.update');
    });
});
