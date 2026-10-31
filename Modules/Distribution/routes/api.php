<?php

use Illuminate\Support\Facades\Route;
use Modules\Distribution\Http\Controllers\UpdateApiController;

/*
| Update API for self-hosted sites. Loaded with ['api', 'module:Distribution',
| AuthenticateSite, throttle] — bearer token, no session, no CSRF.
*/

Route::prefix('api/distribution/v1')->name('distribution.api.')->group(function () {
    Route::post('/check-in', [UpdateApiController::class, 'checkIn'])->name('check-in');
    Route::get('/releases/{release}/download', [UpdateApiController::class, 'download'])->name('download');
    Route::post('/report', [UpdateApiController::class, 'report'])->name('report');
});
