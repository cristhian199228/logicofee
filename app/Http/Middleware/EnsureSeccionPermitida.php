<?php

namespace App\Http\Middleware;

use App\Enums\Seccion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe cada sección a los roles que la tienen en su menú.
 */
class EnsureSeccionPermitida
{
    public function handle(Request $request, Closure $next, string $seccion): Response
    {
        abort_unless(
            $request->user()?->puedeVer(Seccion::from($seccion)) ?? false,
            403,
            'Tu rol no tiene acceso a esta sección.',
        );

        return $next($request);
    }
}
