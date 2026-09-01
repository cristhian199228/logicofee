<?php

namespace App\Enums;

enum Seccion: string
{
    case Catalogo = 'catalogo';
    case Pedido = 'pedido';
    case Historial = 'historial';
    case Seguimiento = 'seguimiento';
    case Lotes = 'lotes';

    public function titulo(): string
    {
        return match ($this) {
            self::Catalogo => 'Catálogo',
            self::Pedido => 'Nuevo pedido',
            self::Historial => 'Historial de Pedidos',
            self::Seguimiento => 'Seguimiento de pedidos',
            self::Lotes => 'Lotes',
        };
    }

    public function ruta(): string
    {
        return match ($this) {
            self::Catalogo => 'catalogo.index',
            self::Pedido => 'pedidos.create',
            self::Historial => 'pedidos.index',
            self::Seguimiento => 'seguimiento.index',
            self::Lotes => 'lotes.index',
        };
    }
}
