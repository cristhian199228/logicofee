<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;

/**
 * Deja en el log de seguridad cada vez que el login se bloquea por exceso de
 * intentos fallidos.
 */
class RegistrarBloqueoDeIngreso
{
    public function handle(Lockout $event): void
    {
        Log::channel('seguridad')->alert('Ingreso bloqueado por exceso de intentos.', [
            'ip' => $event->request->ip(),
            'agente' => $event->request->userAgent(),
        ]);
    }
}
