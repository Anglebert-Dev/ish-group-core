<?php

use Illuminate\Support\Facades\Route;
use Modules\GarmentPack\Http\Controllers\StyleController;

/*
|--------------------------------------------------------------------------
| Garment pack: /app/garments/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/garments')->name('garmentpack.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::prefix('styles')->name('styles.')->group(function () {
            Route::get('/', [StyleController::class, 'index'])->middleware('permission:list garments.style')->name('index');
            Route::get('/create', [StyleController::class, 'create'])->middleware('permission:create garments.style')->name('create');
            Route::post('/', [StyleController::class, 'store'])->middleware('permission:create garments.style')->name('store');
            Route::get('/{style}', [StyleController::class, 'show'])->middleware('permission:read garments.style')->name('show');
            Route::get('/{style}/edit', [StyleController::class, 'edit'])->middleware('permission:update garments.style')->name('edit');
            Route::put('/{style}', [StyleController::class, 'update'])->middleware('permission:update garments.style')->name('update');
        });

        Route::post('/work-orders/{workOrder}/issue-materials', [StyleController::class, 'issue'])
            ->middleware(['permission:create production.entry', 'permission:create inventory.movement'])->name('work-orders.issue');
    });
