<?php

use Illuminate\Support\Facades\Route;
use Modules\Distribution\Http\Controllers\ReleaseController;
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
    Route::post('/sites/{site}/tokens/{token}/revoke', [SiteController::class, 'revokeToken'])->middleware('password.confirm.staff')->name('sites.tokens.revoke');

    Route::get('/releases', [ReleaseController::class, 'index'])->name('releases.index');
    Route::post('/releases', [ReleaseController::class, 'store'])->name('releases.store');
    Route::post('/releases/{release}/yank', [ReleaseController::class, 'yank'])->middleware('password.confirm.staff')->name('releases.yank');
});
