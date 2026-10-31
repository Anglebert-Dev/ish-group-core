<?php

use Illuminate\Support\Facades\Route;
use Modules\Distribution\Http\Controllers\CustomerController;
use Modules\Distribution\Http\Controllers\ReleaseController;
use Modules\Distribution\Http\Controllers\ServerDetailsController;
use Modules\Distribution\Http\Controllers\SiteController;

/*
|--------------------------------------------------------------------------
| Distribution module (our server only)
|--------------------------------------------------------------------------
| Loaded with ['web', 'module:Distribution'].
|
| /app/super-admin/distribution/sites      customer servers and their modules
| /app/super-admin/distribution/releases   signed module versions
*/

$staff = ['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache', 'superadmin'];

Route::prefix('app/super-admin/distribution')->name('superadmin.distribution.')->middleware($staff)->group(function () {
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create', [SiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('/sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
    Route::put('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::post('/sites/{site}/modules', [SiteController::class, 'assign'])->name('sites.modules.assign');
    Route::delete('/sites/{site}/modules/{module}', [SiteController::class, 'unassign'])->middleware('password.confirm.staff')->name('sites.modules.unassign');
    Route::get('/sites/{site}/server', [ServerDetailsController::class, 'edit'])->name('sites.server.edit');
    Route::put('/sites/{site}/server', [ServerDetailsController::class, 'update'])->name('sites.server.update');
    Route::post('/sites/{site}/server/csv', [ServerDetailsController::class, 'upload'])->name('sites.server.upload');
    Route::get('/sites/{site}/server/template.csv', [ServerDetailsController::class, 'template'])->name('sites.server.template');
    Route::get('/sites/{site}/guide', [ServerDetailsController::class, 'guide'])->name('sites.guide');
    Route::post('/sites/{site}/builds', [SiteController::class, 'build'])->name('sites.builds.store');
    Route::delete('/sites/{site}/builds/{build}', [SiteController::class, 'deleteBuild'])->name('sites.builds.destroy');
    Route::post('/sites/{site}/builds/{build}/email', [CustomerController::class, 'emailPackage'])->middleware('throttle:10,1')->name('sites.builds.email');
    Route::post('/sites/{site}/email/preparation', [CustomerController::class, 'emailPreparation'])->middleware('throttle:10,1')->name('sites.email.preparation');
    Route::get('/sites/{site}/builds/{build}/download', [SiteController::class, 'download'])->middleware('password.confirm.staff')->name('sites.builds.download');
    Route::post('/sites/{site}/tokens/{token}/revoke', [SiteController::class, 'revokeToken'])->middleware('password.confirm.staff')->name('sites.tokens.revoke');

    Route::get('/releases', [ReleaseController::class, 'index'])->name('releases.index');
    Route::post('/releases', [ReleaseController::class, 'store'])->name('releases.store');
    Route::post('/releases/signing-key', [ReleaseController::class, 'createKey'])->middleware('password.confirm.staff')->name('releases.key');
    Route::post('/releases/{release}/yank', [ReleaseController::class, 'yank'])->middleware('password.confirm.staff')->name('releases.yank');
});

// Links in the emails to customers: no account, but the signature must be valid.
Route::prefix('self-hosting')->name('distribution.customer.')->middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('/guide/{site}', [CustomerController::class, 'guide'])->name('guide');
    Route::get('/download/{build}', [CustomerController::class, 'download'])->name('download');
});
