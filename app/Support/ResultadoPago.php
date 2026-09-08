<?php

namespace App\Support;

use App\Enums\EstadoPago;

/**
 * Lo que devuelve la pasarela para un intento de cobro.
 */
class ResultadoPago
{
    public function __construct(
        public readonly EstadoPago $estado,
        public readonly ?string $referencia = null,
        public readonly ?string $detalle = null,
        public readonly ?string $motivo = null,
    ) {}

    public function aprobado(): bool
    {
        return $this->estado === EstadoPago::Pagado;
    }

    public function rechazado(): bool
    {
        return $this->estado === EstadoPago::Rechazado;
    }
}
