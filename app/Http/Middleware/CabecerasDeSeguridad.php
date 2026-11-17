<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras que le piden al navegador defender la página: no mostrarla dentro
 * de otros sitios, no adivinar tipos de archivo y no cargar ni enviar nada a
 * dominios ajenos.
 */
class CabecerasDeSeguridad
{
    /**
     * Política de contenido. Alpine (dentro de Livewire) evalúa expresiones y
     * las vistas usan estilos en línea, por eso scripts y estilos admiten
     * 'unsafe-inline'; lo que la política cierra es el origen: ningún script,
     * imagen o petición puede ir a otro dominio.
     *
     * @var list<string>
     */
    private const POLITICA_DE_CONTENIDO = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Con `npm run dev` los recursos llegan desde el servidor de Vite, otro origen.
        if (! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::POLITICA_DE_CONTENIDO));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
