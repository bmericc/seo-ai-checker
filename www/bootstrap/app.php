<?php

use App\Http\Middleware\EnsureUserApproved;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', EnsureUserApproved::class);
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);

        // Ayri bir /login sayfasi yok; ana sayfa (/) zaten girissiz de
        // erisilebilir ve ayni Google ile giris CTA'sini gosteriyor.
        // MCP istemcisinin actigi OAuth onay sayfasinda ise kullaniciyi
        // dogrudan Google girisine gonderiyoruz: Google callback'i
        // redirect()->intended() ile onay sayfasina geri dondurur.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('oauth/authorize')
            ? route('google.redirect')
            : '/');

        // Uygulama her zaman bir reverse proxy'nin (kendi nginx servisimiz,
        // istege bagli olarak onunde nginx-proxy-manager) arkasinda calisir;
        // proxy IP'si sabit/bilinen olmadigindan hepsi guvenilir sayilir.
        // Bu olmadan Laravel gercek istegin HTTPS oldugunu/gercek host'unu
        // bilemez (isSecure(), url() vb. yanlis sonuc doner) ve bu da OAuth
        // "state" dogrulamasi gibi oturuma bagli akislari kirabilir.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp', 'oauth/token', 'oauth/register'),
        );
    })->create();
