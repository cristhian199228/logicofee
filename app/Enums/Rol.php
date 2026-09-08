<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case DireccionGeneral = 'Dirección General';
    case MarketingVentas = 'Marketing y Ventas';
    case LogisticaAlmacen = 'Logística y Almacén';
    case ProduccionOperaciones = 'Producción y Operaciones';
    case Proveedor = 'Proveedor';
    case Cliente = 'Cliente';

    /** Área de la empresa a la que responde el rol, para las pantallas de gestión. */
    public function proposito(): string
    {
        return match ($this) {
            self::Administrador => 'Supervisa todo el sistema y administra las cuentas.',
            self::DireccionGeneral => 'Mira los indicadores de las áreas para decidir.',
            self::MarketingVentas => 'Impulsa el catálogo, las promociones y las ventas.',
            self::LogisticaAlmacen => 'Controla el stock por lotes y el despacho de pedidos.',
            self::ProduccionOperaciones => 'Planifica el tueste y responde por la calidad.',
            self::Proveedor => 'Abastece el almacén y prepara las entregas.',
            self::Cliente => 'Compra en el catálogo y sigue sus pedidos.',
        };
    }

    /**
     * Secciones que el rol puede abrir, en el orden en que aparecen en el menú.
     *
     * @return list<Seccion>
     */
    public function secciones(): array
    {
        return match ($this) {
            self::Administrador => [
                Seccion::Reportes, Seccion::Ventas, Seccion::Almacen, Seccion::Produccion,
                Seccion::Seguimiento, Seccion::Catalogo, Seccion::Productos, Seccion::Pedido,
                Seccion::Historial, Seccion::Lotes, Seccion::Calidad, Seccion::Promociones, Seccion::Usuarios,
            ],
            self::DireccionGeneral => [
                Seccion::Reportes, Seccion::Ventas, Seccion::Almacen, Seccion::Produccion,
                Seccion::Seguimiento, Seccion::Historial, Seccion::Catalogo,
            ],
            self::MarketingVentas => [
                Seccion::Ventas, Seccion::Promociones, Seccion::Productos, Seccion::Catalogo,
                Seccion::Pedido, Seccion::Historial, Seccion::Seguimiento,
            ],
            self::LogisticaAlmacen => [
                Seccion::Almacen, Seccion::Lotes, Seccion::Seguimiento,
                Seccion::Historial, Seccion::Catalogo,
            ],
            self::ProduccionOperaciones => [
                Seccion::Produccion, Seccion::Calidad, Seccion::Lotes, Seccion::Catalogo,
            ],
            self::Proveedor => [
                Seccion::Seguimiento, Seccion::Lotes, Seccion::Calidad, Seccion::Catalogo,
                Seccion::Productos, Seccion::Pedido, Seccion::Historial,
            ],
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

    /** Registrar lotes en el almacén y dar de baja las unidades mermadas. */
    public function puedeMoverAlmacen(): bool
    {
        return in_array($this, [
            self::Administrador, self::Proveedor, self::LogisticaAlmacen, self::ProduccionOperaciones,
        ], true);
    }

    /** Avanzar los pedidos por el flujo Pendiente → Preparación → Entregado (HU03). */
    public function puedeDespacharPedidos(): bool
    {
        return in_array($this, [self::Administrador, self::Proveedor, self::LogisticaAlmacen], true);
    }

    /** Cerrar el cobro de un pedido: efectivo entregado u operación rehecha. */
    public function puedeRegistrarCobros(): bool
    {
        return in_array($this, [
            self::Administrador, self::Proveedor, self::MarketingVentas, self::LogisticaAlmacen,
        ], true);
    }

    /** Registrar el resultado del control de calidad de un lote (HU07). */
    public function puedeControlarCalidad(): bool
    {
        return in_array($this, [self::Administrador, self::Proveedor, self::ProduccionOperaciones], true);
    }

    /** Subir o quitar las fotos con las que el producto sale en el catálogo. */
    public function puedeEditarCatalogo(): bool
    {
        return in_array($this, [self::Administrador, self::Proveedor, self::MarketingVentas], true);
    }

    /** Destacar productos y fijar descuentos vigentes (HU03). */
    public function puedeGestionarPromociones(): bool
    {
        return in_array($this, [self::Administrador, self::MarketingVentas], true);
    }

    /** Cuentas de usuario y roles (HU09). */
    public function esAdministrador(): bool
    {
        return $this === self::Administrador;
    }
}
