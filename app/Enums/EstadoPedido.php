<?php

namespace App\Enums;

enum EstadoPedido: string
{
    case Pendiente = 'Pendiente';
    case Preparacion = 'Preparación';
    case Entregado = 'Entregado';

    /** Siguiente etapa del flujo Pendiente → Preparación → Entregado (HU03). */
    public function siguiente(): ?self
    {
        return match ($this) {
            self::Pendiente => self::Preparacion,
            self::Preparacion => self::Entregado,
            self::Entregado => null,
        };
    }

    /** Texto del botón que avanza el pedido a la siguiente etapa. */
    public function accionSiguiente(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Marcar preparación',
            self::Preparacion => 'Marcar entregado',
            self::Entregado => null,
        };
    }

    /** Clases del chip de estado del tablero de seguimiento. */
    public function clasesChip(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-mostaza-400 text-coffee-900',
            self::Preparacion => 'bg-coffee-300 text-coffee-900',
            self::Entregado => 'bg-coffee-700 text-white',
        };
    }

    /** Color de la barra que encabeza cada columna del tablero. */
    public function claseBarra(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-mostaza-500',
            self::Preparacion => 'bg-coffee-400',
            self::Entregado => 'bg-coffee-700',
        };
    }
}
