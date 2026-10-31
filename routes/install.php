<?php

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

/*
| Installer, only reachable while this copy is not installed
| (RedirectToInstaller returns 404 afterwards). Runs in the "install"
| middleware group: file sessions and CSRF, no database.
*/

Route::prefix('install')->name('install.')->controller(InstallController::class)->group(function () {
    Route::get('/', 'start')->name('start');

    Route::get('/code', 'code')->name('code');
    Route::post('/code', 'checkCode')->middleware('throttle:6,1')->name('code.check');

    Route::get('/requirements', 'requirements')->name('requirements');
    Route::post('/requirements', 'acceptRequirements')->name('requirements.accept');

    Route::get('/database', 'database')->name('database');
    Route::post('/database', 'saveDatabase')->name('database.save');

    Route::get('/app', 'app')->name('app');
    Route::post('/app', 'saveApp')->name('app.save');

    Route::get('/mail', 'mail')->name('mail');
    Route::post('/mail', 'saveMail')->name('mail.save');

    Route::get('/admin', 'admin')->name('admin');
    Route::post('/admin', 'saveAdmin')->name('admin.save');

    Route::get('/review', 'review')->name('review');
    Route::post('/run', 'run')->name('run');
});
