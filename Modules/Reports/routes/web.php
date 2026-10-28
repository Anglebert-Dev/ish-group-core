<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportController;

Route::prefix('app/reports')->name('reports.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/{format}', [ReportController::class, 'export'])->name('export');
    });
