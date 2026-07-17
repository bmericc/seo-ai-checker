<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\KeywordController;
use App\Models\Check;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/privacy-policy', function () {
    return view('legal.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('legal.terms-of-service');
})->name('terms-of-service');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/pending-approval', function () {
    return view('pending-approval');
})->middleware('auth')->name('pending-approval');

Route::middleware('auth')->group(function () {
    Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
    Route::post('/domains/{domain}/check', [DomainController::class, 'check'])->name('domains.check');
    Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

    Route::post('/domains/{domain}/keywords', [KeywordController::class, 'store'])->name('keywords.store');
    Route::get('/keywords/{keyword}', [KeywordController::class, 'show'])->name('keywords.show');
    Route::post('/keywords/{keyword}/check', [KeywordController::class, 'check'])->name('keywords.check');
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    Route::get('/checks/{check}/lighthouse.json', function (Check $check) {
        abort_if($check->lighthouse_raw === null, 404);

        return response()->json($check->lighthouse_raw)
            ->header('Content-Disposition', sprintf('attachment; filename="lighthouse-check-%d.json"', $check->id));
    })->name('checks.lighthouse-raw');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::patch('/users/{user}/revoke', [AdminUserController::class, 'revoke'])->name('users.revoke');
    Route::patch('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});
