<?php

use App\Http\Controllers\DashboardController;
use App\Mcp\Servers\SeoCheckerServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// OAuth kesif (.well-known) ve dinamik istemci kaydi (POST /oauth/register).
// Yetkilendirme ve token uclari Passport'un /oauth/authorize ve /oauth/token
// route'laridir; giris sistemdeki Google girisiyle yapilir.
Mcp::oauthRoutes();

// Sunucu OpenID Connect saglayicisi degil (yalnizca OAuth 2.0, RFC 8414).
// ChatGPT bu adresi de okuyor; burada OAuth belgesini 200 ile vermek eksik
// OIDC alanlariyla bozuk bir OIDC saglayicisi izlenimi veriyordu. HTML 404
// yerine JSON 404 doner.
Route::get('/.well-known/openid-configuration', fn () => response()->json([
    'error' => 'not_found',
    'error_description' => 'OpenID Connect discovery is not supported.',
], 404))->name('mcp.oauth.openid-configuration');

// Her uye yalnizca kendi domainlerini, admin tum domainleri gorur
// (bkz. App\Mcp\Tools\SeoTool).
Mcp::web('/mcp', SeoCheckerServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);

// Teshis denemesi: ChatGPT kok adreste calisan mail-mcp'yi kabul edip
// /mcp'deki sunucuyu kayittan once reddediyor. Ayni sunucu kok adreste de
// sunulur; kok kesif belgesi (/.well-known/oauth-protected-resource) paketin
// varsayilaniyla gelir.
Mcp::web('/', SeoCheckerServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);

// Mcp::web GET / icin 405 kaydeder (SSE dinleme desteklenmiyor). Bunu ezip
// SSE isteyen MCP istemcisine yine 405, tarayiciya tanitim/dashboard sayfasi.
Route::get('/', function (Request $request) {
    if (str_contains((string) $request->header('Accept'), 'text/event-stream')) {
        return response('', 405)->header('Allow', 'POST');
    }

    return app()->call([app(DashboardController::class), 'index']);
})->middleware('web')->name('dashboard');
