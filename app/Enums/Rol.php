<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case Proveedor = 'Proveedor';
    case Cliente = 'Cliente';

    /**
     * Secciones que el rol puede abrir, en el orden en que aparecen en el menú.
     *
     * @return list<Seccion>
     */
    public function secciones(): array
    {
        return match ($this) {
            self::Administrador => [Seccion::Seguimiento, Seccion::Catalogo, Seccion::Pedido, Seccion::Historial, Seccion::Lotes],
            self::Proveedor => [Seccion::Seguimiento, Seccion::Lotes, Seccion::Catalogo, Seccion::Pedido, Seccion::Historial],
            self::Cliente => [Seccion::Catalogo, Seccion::Pedido, Seccion::Historial, Seccion::Seguimiento],
        };
    }

    public function puedeVer(Seccion $seccion): bool
    {
        return in_array($seccion, $this->secciones(), true);
    }

    /** El cliente solo ve los pedidos que registró a su nombre. */
    public function veTodosLosPedidos(): bool
    {
        return $this !== self::Cliente;
    }

    /**
     * Quién mueve el almacén: registrar lotes, subir fotos del catálogo y
     * avanzar los pedidos por el flujo de entrega.
     */
    public function puedeGestionarInventario(): bool
    {
        return $this !== self::Cliente;
    }
}
