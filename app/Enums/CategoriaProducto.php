<?php

namespace App\Enums;

enum CategoriaProducto: string
{
    case EnGrano = 'En Grano';
    case Molido = 'Molido';
    case Blends = 'Blends';

    /** Nombre largo que se muestra en las tarjetas del catálogo. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::EnGrano => 'Café en Grano',
            self::Molido => 'Café Molido',
            self::Blends => 'Blend Especial',
        };
    }
}
