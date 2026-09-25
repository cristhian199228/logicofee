<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

/**
 * Deja en el log de seguridad cada intento de ingreso con credenciales
 * incorrectas, para detectar ataques de fuerza bruta.
 */
class RegistrarIngresoFallido
{
    public function handle(Failed $event): void
    {
        Log::channel('seguridad')->warning('Ingreso fallido.', [
            'usuario' => $event->credentials['username'] ?? null,
            'cuenta_existe' => $event->user !== null,
            'ip' => request()->ip(),
        ]);
    }
}
