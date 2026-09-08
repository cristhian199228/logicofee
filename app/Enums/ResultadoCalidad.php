<?php

namespace App\Enums;

enum ResultadoCalidad: string
{
    case Pendiente = 'Pendiente';
    case Aprobado = 'Aprobado';
    case Rechazado = 'Rechazado';

    /** Resultados que el responsable de producción puede registrar (HU07). */
    public function evaluable(): bool
    {
        return $this !== self::Pendiente;
    }

    /** Un lote rechazado queda bloqueado para la venta. */
    public function bloqueaVenta(): bool
    {
        return $this === self::Rechazado;
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Pendiente => 'Sin control de calidad registrado',
            self::Aprobado => 'Apto para la venta',
            self::Rechazado => 'Bloqueado para la venta',
        };
    }

    /** Clases del chip que acompaña a cada lote. */
    public function clasesChip(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-coffee-100 text-coffee-700',
            self::Aprobado => 'bg-coffee-700 text-white',
            self::Rechazado => 'bg-ladrillo-500 text-white',
        };
    }
}
