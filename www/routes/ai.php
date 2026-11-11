<?php

use App\Mcp\Servers\SeoCheckerServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth kesif (.well-known) ve dinamik istemci kaydi (POST /oauth/register).
// Yetkilendirme ve token uclari Passport'un /oauth/authorize ve /oauth/token
// route'laridir; giris sistemdeki Google girisiyle yapilir.
Mcp::oauthRoutes();

// Her uye yalnizca kendi domainlerini, admin tum domainleri gorur
// (bkz. App\Mcp\Tools\SeoTool).
Mcp::web('/mcp', SeoCheckerServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);

// Connector adresi olarak yalnizca site adresi (https://site) girildiginde
// de calissin; bkz. McpRegistrar::webAtRoot.
app(\Laravel\Mcp\Server\Registrar::class)->webAtRoot(SeoCheckerServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);
