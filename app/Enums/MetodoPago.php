<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Efectivo = 'Efectivo';
    case Tarjeta = 'Tarjeta';
    case Yape = 'Yape';

    public function titulo(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Tarjeta => 'Tarjeta de crédito o débito',
            self::Yape => 'Yape',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Efectivo => 'Se cobra al momento de la entrega.',
            self::Tarjeta => 'Se autoriza el cargo al registrar el pedido.',
            self::Yape => 'Se confirma la operación al registrar el pedido.',
        };
    }

    /** El efectivo se cobra recién cuando el pedido llega al cliente. */
    public function seCobraAlEntregar(): bool
    {
        return $this === self::Efectivo;
    }

    public function requiereTarjeta(): bool
    {
        return $this === self::Tarjeta;
    }

    public function requiereCelular(): bool
    {
        return $this === self::Yape;
    }

    /** Clases del chip con el que el método aparece en las pantallas. */
    public function clasesChip(): string
    {
        return match ($this) {
            self::Efectivo => 'bg-coffee-100 text-coffee-700',
            self::Tarjeta => 'bg-coffee-700 text-white',
            self::Yape => 'bg-[#742384] text-white',
        };
    }
}
