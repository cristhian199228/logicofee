<?php

namespace App\Enums;

enum TipoEntrega: string
{
    case Delivery = 'Delivery';
    case RecojoEnTienda = 'Recojo en tienda';

    public function titulo(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery a domicilio',
            self::RecojoEnTienda => 'Recojo en tienda',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Delivery => 'El pedido se lleva a la dirección del cliente.',
            self::RecojoEnTienda => 'El cliente lo recoge en el local, sin costo de envío.',
        };
    }

    /** Solo el delivery paga el cargo de envío del pedido. */
    public function costoEnvio(): float
    {
        return match ($this) {
            self::Delivery => (float) config('logicoffee.envio'),
            self::RecojoEnTienda => 0.0,
        };
    }

    public function requiereDireccion(): bool
    {
        return $this === self::Delivery;
    }
}
