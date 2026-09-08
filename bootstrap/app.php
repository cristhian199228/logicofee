<?php

use App\Http\Middleware\EnsureCuentaActiva;
use App\Http\Middleware\EnsureSeccionPermitida;
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
        // Detrás de un túnel (Cloudflare, ngrok) el esquema real llega en
        // X-Forwarded-Proto; sin esto los assets se piden por http y el
        // navegador los bloquea como contenido mixto.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'seccion' => EnsureSeccionPermitida::class,
            'cuenta.activa' => EnsureCuentaActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
