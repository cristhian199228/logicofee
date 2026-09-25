<?php

namespace App\Http\Middleware;

use App\Enums\Seccion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe cada sección a los roles que la tienen en su menú y deja en el log
 * de seguridad los intentos de entrar a una sección ajena.
 */
class EnsureSeccionPermitida
{
    public function handle(Request $request, Closure $next, string $seccion): Response
    {
        if (! ($request->user()?->puedeVer(Seccion::from($seccion)) ?? false)) {
            Log::channel('seguridad')->warning('Acceso denegado a una sección.', [
                'usuario' => $request->user()?->username,
                'rol' => $request->user()?->rol->value,
                'seccion' => $seccion,
                'ip' => $request->ip(),
            ]);

            abort(403, 'Tu rol no tiene acceso a esta sección.');
        }

        return $next($request);
    }
}
