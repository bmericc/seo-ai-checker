<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\BingController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainMemberController;
use App\Http\Controllers\IndexNowController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LighthouseReportController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\McpConnectionController;
use App\Http\Controllers\PromptExplorerController;
use App\Http\Controllers\ReportShareController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/privacy-policy', function () {
    return view('legal.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('legal.terms-of-service');
})->name('terms-of-service');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::get('/auth/bing/connect', [BingController::class, 'connect'])->middleware('auth')->name('bing.connect');
Route::get('/auth/bing/callback', [BingController::class, 'callback'])->middleware('auth')->name('bing.callback');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Paylasilan rapor linki: giris gerektirmez (bkz. ReportShareController::show).
Route::get('/r/{token}', [ReportShareController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:60,1')
    ->name('reports.public');

Route::get('/pending-approval', function () {
    return view('pending-approval');
})->middleware('auth')->name('pending-approval');

Route::middleware('auth')->group(function () {
    Route::get('/connections', function () {
        return view('connections');
    })->name('connections');
    Route::view('/settings', 'settings')->name('settings');
    Route::delete('/connections/mcp/{client}', [McpConnectionController::class, 'destroy'])->name('connections.mcp.destroy');

    Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
    Route::post('/domains/{domain}/check', [DomainController::class, 'check'])->name('domains.check');
    Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::post('/domains/{domain}/keyword-suggestions/dismiss', [DomainController::class, 'dismissKeywordSuggestion'])->name('domains.keyword-suggestions.dismiss');
    Route::patch('/domains/{domain}/ga4-property', [DomainController::class, 'updateGa4Property'])->name('domains.ga4-property.update');
    Route::post('/domains/{domain}/ga4-properties/fetch', [DomainController::class, 'fetchGa4Properties'])->name('domains.ga4-properties.fetch');
    Route::post('/domains/{domain}/whois/refresh', [DomainController::class, 'refreshWhois'])->name('domains.whois.refresh');
    Route::patch('/domains/{domain}/llm-visibility', [DomainController::class, 'updateLlmVisibility'])->name('domains.llm-visibility.update');
    Route::patch('/domains/{domain}/llm-scraper', [DomainController::class, 'updateLlmScraper'])->name('domains.llm-scraper.update');
    Route::post('/domains/{domain}/llm-mentions/fetch', [DomainController::class, 'fetchLlmMentions'])->name('domains.llm-mentions.fetch');
    Route::post('/domains/{domain}/keyword-metrics/refresh', [DomainController::class, 'refreshKeywordMetrics'])->name('domains.keyword-metrics.refresh');
    Route::post('/domains/{domain}/llm-api-keys', [DomainController::class, 'storeLlmApiKey'])->name('domains.llm-api-keys.store');
    Route::delete('/domains/{domain}/llm-api-keys/{llmApiKey}', [DomainController::class, 'destroyLlmApiKey'])->name('domains.llm-api-keys.destroy');
    Route::get('/domains/{domain}/lighthouse-report', [LighthouseReportController::class, 'show'])->name('domains.lighthouse-report');
    Route::post('/domains/{domain}/lighthouse-report/start', [LighthouseReportController::class, 'start'])->name('domains.lighthouse-report.start');
    Route::post('/domains/{domain}/lighthouse-report/start-onpage', [LighthouseReportController::class, 'startOnPage'])->name('domains.lighthouse-report.start-onpage');
    Route::get('/domains/{domain}/llms-txt', [LlmsTxtController::class, 'show'])->name('domains.llms-txt');
    Route::get('/domains/{domain}/llms-txt/download', [LlmsTxtController::class, 'download'])->name('domains.llms-txt.download');

    Route::get('/domains/{domain}/indexnow/key-file', [IndexNowController::class, 'keyFile'])->name('domains.indexnow.key-file');
    Route::post('/domains/{domain}/indexnow/regenerate', [IndexNowController::class, 'regenerate'])->name('domains.indexnow.regenerate');
    Route::post('/domains/{domain}/indexnow/verify', [IndexNowController::class, 'verify'])->name('domains.indexnow.verify');
    Route::post('/domains/{domain}/indexnow/submit', [IndexNowController::class, 'submit'])->name('domains.indexnow.submit');

    Route::post('/domains/{domain}/prompts', [PromptExplorerController::class, 'store'])->name('domains.prompts.store');
    Route::post('/domains/{domain}/prompts/suggest', [PromptExplorerController::class, 'suggest'])->name('domains.prompts.suggest');
    Route::get('/domains/{domain}/prompts/{exploration}', [PromptExplorerController::class, 'show'])->name('domains.prompts.show');

    Route::post('/domains/{domain}/shares', [ReportShareController::class, 'store'])->name('domains.shares.store');
    Route::delete('/domains/{domain}/shares/{share}', [ReportShareController::class, 'destroy'])->name('domains.shares.destroy');

    Route::post('/domains/{domain}/members', [DomainMemberController::class, 'store'])->name('domains.members.store');
    Route::patch('/domains/{domain}/members/{member}', [DomainMemberController::class, 'update'])->name('domains.members.update');
    Route::delete('/domains/{domain}/members/{member}', [DomainMemberController::class, 'destroy'])->name('domains.members.destroy');

    Route::post('/domains/{domain}/keywords', [KeywordController::class, 'store'])->name('keywords.store');
    Route::get('/keywords/{keyword}', [KeywordController::class, 'show'])->name('keywords.show');
    Route::post('/keywords/{keyword}/check', [KeywordController::class, 'check'])->name('keywords.check');
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    Route::get('/checks/{check}/lighthouse.json', [CheckController::class, 'lighthouseRaw'])->name('checks.lighthouse-raw');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::patch('/users/{user}/revoke', [AdminUserController::class, 'revoke'])->name('users.revoke');
    Route::patch('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});
