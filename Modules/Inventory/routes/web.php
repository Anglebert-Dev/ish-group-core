<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\ItemController;
use Modules\Inventory\Http\Controllers\MovementController;
use Modules\Inventory\Http\Controllers\SettingsController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StoreController;

/*
|--------------------------------------------------------------------------
| Inventory: /app/inventory/...
|--------------------------------------------------------------------------
| Loaded with ['web', 'module:Inventory'], so every route here is hidden
| while the module is not installed or not in the business's package.
*/

Route::prefix('app/inventory')->name('inventory.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::prefix('items')->name('items.')->group(function () {
            Route::get('/', [ItemController::class, 'index'])->middleware('permission:list inventory.item')->name('index');
            Route::get('/export/pdf', [ItemController::class, 'exportPdf'])->middleware('permission:list inventory.item')->name('export.pdf');
            Route::get('/export/excel', [ItemController::class, 'exportExcel'])->middleware('permission:list inventory.item')->name('export.excel');
            Route::get('/create', [ItemController::class, 'create'])->middleware('permission:create inventory.item')->name('create');
            Route::post('/', [ItemController::class, 'store'])->middleware('permission:create inventory.item')->name('store');
            Route::get('/{item}', [ItemController::class, 'show'])->middleware('permission:read inventory.item')->name('show');
            Route::get('/{item}/edit', [ItemController::class, 'edit'])->middleware('permission:update inventory.item')->name('edit');
            Route::put('/{item}', [ItemController::class, 'update'])->middleware('permission:update inventory.item')->name('update');
            Route::put('/{item}/status', [ItemController::class, 'updateStatus'])->middleware('permission:update inventory.item')->name('status');
            Route::delete('/{item}', [ItemController::class, 'destroy'])->middleware(['permission:delete inventory.item', 'password.confirm.staff'])->name('destroy');
            Route::post('/{item}/stock', [StockController::class, 'store'])->middleware('permission:create inventory.movement')->name('stock');
        });

        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->middleware('permission:list inventory.store')->name('index');
            Route::post('/', [StoreController::class, 'store'])->middleware(['permission:create inventory.store', 'quota:inventory.stores'])->name('store');
            Route::put('/{store}', [StoreController::class, 'update'])->middleware('permission:update inventory.store')->name('update');
            Route::put('/{store}/default', [StoreController::class, 'makeDefault'])->middleware('permission:update inventory.store')->name('default');
            Route::delete('/{store}', [StoreController::class, 'destroy'])->middleware(['permission:delete inventory.store', 'password.confirm.staff'])->name('destroy');
        });

        Route::get('/movements', [MovementController::class, 'index'])->middleware('permission:list inventory.movement')->name('movements.index');
        Route::get('/movements/export/pdf', [MovementController::class, 'exportPdf'])->middleware('permission:list inventory.movement')->name('movements.export.pdf');
        Route::get('/movements/export/excel', [MovementController::class, 'exportExcel'])->middleware('permission:list inventory.movement')->name('movements.export.excel');

        Route::get('/settings', [SettingsController::class, 'edit'])->middleware('permission:update inventory.setting')->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:update inventory.setting')->name('settings.update');
    });
