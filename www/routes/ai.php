<?php

use App\Mcp\Servers\SeoCheckerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// MCP adresi yalnizca /mcp. Kok adres icin kesif belgesi verilmez ki
// connector'a site adresi (sonunda /mcp olmadan) girildiginde giris
// ekrani acilmadan hata alinsin. Paket bu route zaten varsa kendi kok
// belgesini eklemez; bu yuzden oauthRoutes()'tan once tanimlanir.
Route::get('/.well-known/oauth-protected-resource', fn () => response()->json([
    'error' => 'not_found',
    'error_description' => 'MCP server address is '.url('/mcp'),
], 404))->name('mcp.oauth.protected-resource');

// Site adresine gelen MCP istegine (POST /) dogru adresi soyleyen hata.
Route::post('/', fn () => response()->json([
    'jsonrpc' => '2.0',
    'id' => request()->json('id'),
    'error' => [
        'code' => -32601,
        'message' => 'MCP server is not at this address. Use '.url('/mcp'),
    ],
], 404));

// OAuth kesif (.well-known) ve dinamik istemci kaydi (POST /oauth/register).
// Yetkilendirme ve token uclari Passport'un /oauth/authorize ve /oauth/token
// route'laridir; giris sistemdeki Google girisiyle yapilir.
Mcp::oauthRoutes();

// Her uye yalnizca kendi domainlerini, admin tum domainleri gorur
// (bkz. App\Mcp\Tools\SeoTool).
Mcp::web('/mcp', SeoCheckerServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);
