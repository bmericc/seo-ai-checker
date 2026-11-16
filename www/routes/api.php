<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DomainController;
use Illuminate\Support\Facades\Route;

// Mobil uygulamanin (Seo Checker) salt okunur API'si. Token,
// /mobile/auth/start'in verdigi tek kullanimlik kodla alinir; bkz.
// Api\V1\AuthController.
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/token', [AuthController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('auth.token');

    Route::middleware(['auth:api', 'mobile.api', 'throttle:120,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::delete('/auth/token', [AuthController::class, 'destroy'])->name('auth.logout');
        Route::delete('/me', [AuthController::class, 'destroyAccount'])->middleware('throttle:10,1')->name('me.destroy');
        Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
        Route::get('/domains/{domain}/screen', [DomainController::class, 'screen'])->whereNumber('domain')->name('domains.screen');
        Route::get('/changes', [DomainController::class, 'changes'])->name('changes');
    });
});
