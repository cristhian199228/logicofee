<?php

namespace App\Enums;

enum EstadoPago: string
{
    case Pendiente = 'Pendiente';
    case Pagado = 'Pagado';
    case Rechazado = 'Rechazado';

    public function descripcion(): string
    {
        return match ($this) {
            self::Pendiente => 'Por cobrar',
            self::Pagado => 'Cobrado',
            self::Rechazado => 'La operación no se aprobó',
        };
    }

    public function clasesChip(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-mostaza-400 text-coffee-900',
            self::Pagado => 'bg-coffee-700 text-white',
            self::Rechazado => 'bg-ladrillo-500 text-white',
        };
    }
}
