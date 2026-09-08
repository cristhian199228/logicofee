<?php

namespace App\Enums;

enum Seccion: string
{
    case Catalogo = 'catalogo';
    case Pedido = 'pedido';
    case Historial = 'historial';
    case Seguimiento = 'seguimiento';
    case Lotes = 'lotes';
    case Calidad = 'calidad';
    case Promociones = 'promociones';
    case Reportes = 'reportes';
    case Usuarios = 'usuarios';
    case Ventas = 'ventas';
    case Almacen = 'almacen';
    case Produccion = 'produccion';
    case Productos = 'productos';

    /** Etiqueta corta con la que la sección aparece en el menú superior. */
    public function titulo(): string
    {
        return match ($this) {
            self::Catalogo => 'Catálogo',
            self::Pedido => 'Nuevo pedido',
            self::Historial => 'Historial',
            self::Seguimiento => 'Seguimiento',
            self::Lotes => 'Lotes',
            self::Calidad => 'Calidad',
            self::Promociones => 'Promociones',
            self::Reportes => 'Panel',
            self::Usuarios => 'Usuarios',
            self::Ventas => 'Ventas',
            self::Almacen => 'Almacén',
            self::Produccion => 'Producción',
            self::Productos => 'Productos',
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
            self::Calidad => 'calidad.index',
            self::Promociones => 'promociones.index',
            self::Reportes => 'reportes.index',
            self::Usuarios => 'usuarios.index',
            self::Ventas => 'ventas.index',
            self::Almacen => 'almacen.index',
            self::Produccion => 'produccion.index',
            self::Productos => 'productos.index',
        };
    }
}
