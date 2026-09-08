<?php

namespace App\Support\Reportes;

use App\Enums\CategoriaProducto;
use App\Enums\EstadoPedido;
use App\Enums\PeriodoReporte;
use App\Enums\ResultadoCalidad;
use App\Enums\Rol;
use App\Enums\Seccion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use App\Support\PlanProduccion;
use App\Support\ResumenAlmacen;
use App\Support\ResumenComercial;
use App\Support\ResumenIndicadores;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el reporte que le corresponde a cada sección del menú. Reutiliza los
 * mismos resúmenes que alimentan las pantallas, para que lo descargado diga
 * exactamente lo mismo que se ve en la aplicación.
 */
class CatalogoDeReportes
{
    /**
     * @param  array<string, mixed>  $parametros  Filtros que traía la pantalla (periodo, búsqueda, categoría).
     */
    public function __construct(
        private User $usuario,
        private array $parametros = [],
    ) {}

    public function para(Seccion $seccion): Reporte
    {
        return match ($seccion) {
            Seccion::Reportes => $this->panelDeIndicadores(),
            Seccion::Ventas => $this->panelComercial(),
            Seccion::Almacen => $this->panelDeAlmacen(),
            Seccion::Produccion => $this->planDeProduccion(),
            Seccion::Catalogo => $this->catalogo(),
            Seccion::Productos => $this->productos(),
            Seccion::Pedido => $this->cotizacion(),
            Seccion::Historial => $this->historial(),
            Seccion::Seguimiento => $this->seguimiento(),
            Seccion::Lotes => $this->lotes(),
            Seccion::Calidad => $this->calidad(),
            Seccion::Promociones => $this->promociones(),
            Seccion::Usuarios => $this->usuarios(),
        };
    }

    private function panelDeIndicadores(): Reporte
    {
        $resumen = new ResumenIndicadores($periodo = $this->periodo(PeriodoReporte::Dia));

        return $this->reporte(
            'Reporte gerencial',
            'Pedidos, ventas y productos más vendidos para la toma de decisiones ('.mb_strtolower($periodo->titulo()).').',
            [
                IndicadorReporte::moneda('Ventas registradas', $resumen->ventasTotales(), 'Total facturado, envío incluido'),
                IndicadorReporte::cantidad('Pedidos', $resumen->totalPedidos(), 'Registrados en el sistema'),
                IndicadorReporte::moneda('Ticket promedio', $resumen->ticketPromedio(), 'Venta media por pedido'),
                IndicadorReporte::cantidad('Unidades vendidas', $resumen->unidadesVendidas(), 'Bolsas despachadas'),
                IndicadorReporte::moneda('Por cobrar', $resumen->porCobrar(), 'Pedidos con el cobro abierto'),
            ],
            [
                $this->tablaDeVentasPorTramo($resumen, $periodo),
                new TablaReporte(
                    'Pedidos por estado',
                    [ColumnaReporte::texto('Estado', 2), ColumnaReporte::numero('Pedidos'), ColumnaReporte::moneda('Importe')],
                    $resumen->pedidosPorEstado()
                        ->map(fn (array $fila) => [$fila['estado']->value, $fila['cantidad'], $fila['total']])
                        ->all(),
                ),
                $this->tablaDeMasVendidos($resumen),
                new TablaReporte(
                    'Alertas de stock',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.2), ColumnaReporte::numero('Stock'),
                        ColumnaReporte::numero('Mínimo'), ColumnaReporte::texto('Situación', 1.2),
                    ],
                    $resumen->alertasDeStock()->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->categoria->value,
                        $producto->stock, $producto->stock_minimo,
                        $producto->agotado() ? 'Agotado' : 'En el mínimo',
                    ])->all(),
                    nota: 'Lotes bloqueados por calidad: '.$resumen->lotesBloqueados()
                        .' · Lotes por controlar: '.$resumen->lotesSinEvaluar().'.',
                    vacia: 'Todo el catálogo está por encima de su stock mínimo.',
                ),
            ],
        );
    }

    private function panelComercial(): Reporte
    {
        $indicadores = new ResumenIndicadores($periodo = $this->periodo(PeriodoReporte::Semana));
        $comercial = new ResumenComercial;
        $descuento = $comercial->descuentoEntregado();
        $ventasTotales = $indicadores->ventasTotales();

        return $this->reporte(
            'Reporte comercial',
            'Evolución de las ventas, clientes que más compran y rendimiento de las promociones.',
            [
                IndicadorReporte::moneda('Ventas registradas', $ventasTotales, $indicadores->totalPedidos().' pedidos'),
                IndicadorReporte::moneda('Ticket promedio', $indicadores->ticketPromedio(), 'Venta media por pedido'),
                IndicadorReporte::cantidad('Clientes atendidos', $comercial->clientesAtendidos(), $indicadores->unidadesVendidas().' unidades despachadas'),
                IndicadorReporte::moneda('Descuento entregado', $descuento['descuento'], $descuento['unidades'].' uds vendidas en promoción'),
                IndicadorReporte::moneda('Por cobrar', $comercial->porCobrar(), 'Cobros todavía abiertos'),
            ],
            [
                $this->tablaDeVentasPorTramo($indicadores, $periodo),
                new TablaReporte(
                    'Clientes que más compran',
                    [
                        ColumnaReporte::texto('Cliente', 2.4), ColumnaReporte::texto('Tipo', 1.4),
                        ColumnaReporte::numero('Pedidos'), ColumnaReporte::moneda('Total comprado'),
                        ColumnaReporte::fecha('Última compra'),
                    ],
                    $comercial->mejoresClientes(15)->map(fn (array $fila) => [
                        $fila['nombre'], $fila['tipo'], $fila['pedidos'], $fila['total'],
                        $fila['ultima'] === '' ? null : Carbon::parse($fila['ultima']),
                    ])->all(),
                    vacia: 'Todavía no hay pedidos registrados.',
                ),
                new TablaReporte(
                    'Ventas por tipo de cliente',
                    [
                        ColumnaReporte::texto('Tipo de cliente', 2), ColumnaReporte::numero('Pedidos'),
                        ColumnaReporte::moneda('Total'), ColumnaReporte::porcentaje('Participación'),
                    ],
                    $comercial->ventasPorTipoDeCliente()->map(fn (array $fila) => [
                        $fila['tipo'], $fila['pedidos'], $fila['total'],
                        $ventasTotales > 0 ? round($fila['total'] / $ventasTotales * 100, 1) : 0,
                    ])->all(),
                ),
                new TablaReporte(
                    'Ventas por método de pago',
                    [
                        ColumnaReporte::texto('Método', 2), ColumnaReporte::numero('Pedidos'),
                        ColumnaReporte::moneda('Total'), ColumnaReporte::moneda('Por cobrar'),
                    ],
                    $comercial->ventasPorMetodoDePago()->map(fn (array $fila) => [
                        $fila['metodo']->value, $fila['pedidos'], $fila['total'], $fila['porCobrar'],
                    ])->all(),
                ),
                $this->tablaDeMasVendidos($indicadores),
                $this->tablaDePromociones($comercial->promocionesVigentes()),
                new TablaReporte(
                    'Catálogo sin ventas',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.4), ColumnaReporte::moneda('Precio'),
                        ColumnaReporte::numero('Stock'),
                    ],
                    $comercial->productosSinVentas()->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->categoria->value,
                        (float) $producto->precio, $producto->stock,
                    ])->all(),
                    nota: 'Productos que todavía no aparecen en ningún pedido: candidatos a promoción.',
                    vacia: 'Todo el catálogo registró al menos una venta.',
                ),
            ],
        );
    }

    private function panelDeAlmacen(): Reporte
    {
        $almacen = new ResumenAlmacen;

        return $this->reporte(
            'Reporte de almacén',
            'Stock por lote, vencimientos, mermas y la reposición que toca pedir a producción.',
            [
                IndicadorReporte::cantidad('Unidades vendibles', $almacen->unidadesDisponibles(), $almacen->lotesActivos().' lotes con stock'),
                IndicadorReporte::moneda('Valor del inventario', $almacen->valorInventario(), 'A precio de lista'),
                IndicadorReporte::cantidad('Pedidos por despachar', $almacen->pedidosPorDespachar(), 'Pendientes y en preparación'),
                IndicadorReporte::cantidad('Unidades dadas de baja', $almacen->unidadesMermadas(), 'Merma acumulada del almacén'),
                IndicadorReporte::cantidad('Lotes por vencer', $almacen->lotesPorVencer()->count(), 'Dentro de '.Lote::DIAS_AVISO_VENCIMIENTO.' días'),
            ],
            [
                new TablaReporte(
                    'Reposición sugerida',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::numero('Stock'), ColumnaReporte::numero('Mínimo'),
                        ColumnaReporte::numero('Reponer'), ColumnaReporte::numero('Días de cobertura', 1.3),
                    ],
                    $almacen->reposicionSugerida()->map(fn (array $fila) => [
                        $fila['producto']->nombre, $fila['producto']->presentacion,
                        $fila['producto']->stock, $fila['producto']->stock_minimo,
                        $fila['sugerido'], $fila['dias'],
                    ])->all(),
                    nota: 'Unidades necesarias para dejar cada producto al doble de su stock mínimo.',
                    vacia: 'Todo el catálogo está por encima de su stock mínimo.',
                ),
                $this->tablaDeLotes(
                    'Lotes por vencer',
                    $almacen->lotesPorVencer(),
                    'Lotes con unidades que vencen dentro de los próximos '.Lote::DIAS_AVISO_VENCIMIENTO.' días.',
                    'Ningún lote con stock vence en la ventana de aviso.',
                ),
                $this->tablaDeLotes(
                    'Lotes vencidos con stock',
                    $almacen->lotesVencidos(),
                    'Unidades vencidas que siguen ocupando lugar: candidatas a baja.',
                    'No hay lotes vencidos con unidades en almacén.',
                ),
                new TablaReporte(
                    'Cobertura por producto',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::numero('Stock'), ColumnaReporte::numero('Mínimo'),
                        ColumnaReporte::numero('Consumo diario', 1.3), ColumnaReporte::numero('Días de cobertura', 1.3),
                        ColumnaReporte::moneda('Valor en stock', 1.3),
                    ],
                    $almacen->coberturaPorProducto()->map(fn (array $fila) => [
                        $fila['producto']->nombre, $fila['producto']->presentacion,
                        $fila['producto']->stock, $fila['producto']->stock_minimo,
                        $fila['consumo'], $fila['dias'],
                        $fila['producto']->stock * (float) $fila['producto']->precio,
                    ])->all(),
                    nota: 'Consumo estimado con lo vendido en los últimos '.ResumenAlmacen::DIAS_DE_CONSUMO.' días.',
                ),
                $this->tablaDeLotes(
                    'Inventario por lote',
                    Lote::query()->with('producto')->disponibles()->porVencimiento()->get(),
                    'Todo lo que hoy tiene unidades en almacén, en orden de despacho.',
                    'El almacén no tiene lotes con unidades.',
                ),
            ],
        );
    }

    private function planDeProduccion(): Reporte
    {
        $plan = new PlanProduccion;
        $rendimiento = $plan->rendimientoCalidad();
        $evaluados = $rendimiento['aprobados'] + $rendimiento['rechazados'];

        return $this->reporte(
            'Reporte de producción',
            'Órdenes de tueste sugeridas, lotes por controlar y rendimiento del área.',
            [
                IndicadorReporte::cantidad('Unidades por tostar', $plan->unidadesSugeridas(), $plan->ordenesSugeridas()->count().' productos en el mínimo'),
                IndicadorReporte::cantidad('Producción reciente', $plan->unidadesProducidas(), 'Tostadas en '.PlanProduccion::DIAS_DE_PRODUCCION.' días'),
                new IndicadorReporte('Lotes aprobados', $rendimiento['porcentaje'].'%', $rendimiento['aprobados'].' aprobados · '.$rendimiento['rechazados'].' rechazados'),
                IndicadorReporte::cantidad('Unidades bloqueadas', $plan->unidadesBloqueadas(), 'Rechazadas en control de calidad'),
                new IndicadorReporte('Merma', $plan->porcentajeDeMerma().'%', 'Sobre el total producido'),
            ],
            [
                new TablaReporte(
                    'Órdenes de tueste sugeridas',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.2), ColumnaReporte::numero('Stock'),
                        ColumnaReporte::numero('Mínimo'), ColumnaReporte::numero('Tostar'),
                        ColumnaReporte::texto('Prioridad', 1.2),
                    ],
                    $plan->ordenesSugeridas()->map(fn (array $fila) => [
                        $fila['producto']->nombre, $fila['producto']->presentacion,
                        $fila['producto']->categoria->value, $fila['producto']->stock,
                        $fila['producto']->stock_minimo, $fila['sugerido'],
                        $fila['urgente'] ? 'Urgente (agotado)' : 'Normal',
                    ])->all(),
                    vacia: 'No hay nada urgente por tostar: todo el catálogo está sobre su stock mínimo.',
                ),
                $this->tablaDeLotes(
                    'Lotes esperando control de calidad',
                    $plan->lotesPendientes(),
                    'Lotes tostados con unidades y sin resultado de control registrado.',
                    'No quedan lotes por evaluar.',
                ),
                new TablaReporte(
                    'Últimos lotes tostados',
                    [
                        ColumnaReporte::texto('Lote', 1.2), ColumnaReporte::texto('Producto', 2.2),
                        ColumnaReporte::fecha('Tostado'), ColumnaReporte::fecha('Vence'),
                        ColumnaReporte::numero('Inicial'), ColumnaReporte::numero('Disponible'),
                        ColumnaReporte::texto('Calidad', 1.2), ColumnaReporte::texto('Evaluado por', 1.6),
                    ],
                    $plan->ultimosLotes(20)->map(fn (Lote $lote) => [
                        $lote->codigo, $lote->producto->nombre, $lote->tostado_at, $lote->vence_at,
                        $lote->cantidad_inicial, $lote->cantidad_disponible,
                        $lote->calidad->value, $lote->evaluador?->name,
                    ])->all(),
                    vacia: 'Todavía no se registró ningún lote de producción.',
                ),
                new TablaReporte(
                    'Rendimiento del control de calidad',
                    [
                        ColumnaReporte::texto('Resultado', 2), ColumnaReporte::numero('Lotes'),
                        ColumnaReporte::porcentaje('Sobre lo evaluado'),
                    ],
                    [
                        [ResultadoCalidad::Aprobado->value, $rendimiento['aprobados'], $evaluados > 0 ? round($rendimiento['aprobados'] / $evaluados * 100, 1) : 0],
                        [ResultadoCalidad::Rechazado->value, $rendimiento['rechazados'], $evaluados > 0 ? round($rendimiento['rechazados'] / $evaluados * 100, 1) : 0],
                        [ResultadoCalidad::Pendiente->value, $rendimiento['pendientes'], null],
                    ],
                ),
            ],
        );
    }

    private function catalogo(): Reporte
    {
        $busqueda = $this->texto('q');
        $categoria = CategoriaProducto::tryFrom($this->texto('categoria'));

        $productos = Producto::query()
            ->buscar($busqueda)
            ->deCategoria($categoria)
            ->orderBy('nombre')
            ->get();

        $filtros = collect([
            $busqueda === '' ? null : 'búsqueda "'.$busqueda.'"',
            $categoria?->etiqueta(),
        ])->filter()->implode(' · ');

        return $this->reporte(
            'Reporte del catálogo',
            $filtros === '' ? 'Catálogo completo de LogiCoffee con precios y disponibilidad.' : 'Catálogo filtrado por '.$filtros.'.',
            [
                IndicadorReporte::cantidad('Productos', $productos->count(), $filtros === '' ? 'Catálogo completo' : 'Con los filtros aplicados'),
                IndicadorReporte::cantidad('En promoción', $productos->filter->promocionVigente()->count(), 'Con descuento vigente hoy'),
                IndicadorReporte::cantidad('Sin stock suficiente', $productos->filter(fn (Producto $producto) => $producto->agotado() || $producto->bajoStock())->count(), 'Agotados o en el mínimo'),
                IndicadorReporte::moneda('Valor del catálogo', $productos->sum(fn (Producto $producto) => $producto->stock * (float) $producto->precio), 'Stock a precio de lista'),
            ],
            [
                new TablaReporte(
                    'Productos del catálogo',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.3), ColumnaReporte::moneda('Precio de lista'),
                        ColumnaReporte::moneda('Precio vigente'), ColumnaReporte::porcentaje('Descuento'),
                        ColumnaReporte::numero('Stock'), ColumnaReporte::texto('Disponibilidad', 1.4),
                    ],
                    $productos->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->categoria->value,
                        (float) $producto->precio, $producto->precioVigente(),
                        $producto->tieneDescuento() ? $producto->descuento : null,
                        $producto->stock, $this->disponibilidad($producto),
                    ])->all(),
                    vacia: 'Ningún producto coincide con los filtros elegidos.',
                ),
                $this->tablaDePromociones(
                    Producto::query()->enPromocion()->withSum('lineas as unidades_vendidas', 'cantidad')->orderBy('nombre')->get()
                ),
            ],
        );
    }

    private function productos(): Reporte
    {
        $productos = Producto::query()->withCount('lotes')->orderBy('nombre')->get();

        return $this->reporte(
            'Reporte de productos',
            'Estado del catálogo que administra el área: precios, stock y promociones.',
            [
                IndicadorReporte::cantidad('Productos', $productos->count(), 'Registrados en el catálogo'),
                IndicadorReporte::cantidad('Destacados', $productos->where('destacado', true)->count(), 'Marcados para promoción'),
                IndicadorReporte::cantidad('Bajo stock', $productos->filter(fn (Producto $producto) => $producto->agotado() || $producto->bajoStock())->count(), 'Agotados o en el mínimo'),
                IndicadorReporte::moneda('Valor del inventario', $productos->sum(fn (Producto $producto) => $producto->stock * (float) $producto->precio), 'Stock a precio de lista'),
            ],
            [
                new TablaReporte(
                    'Catálogo administrado',
                    [
                        ColumnaReporte::texto('Producto', 2.2), ColumnaReporte::texto('Presentación', 1.1),
                        ColumnaReporte::texto('Categoría', 1.2), ColumnaReporte::moneda('Precio'),
                        ColumnaReporte::numero('Stock'), ColumnaReporte::numero('Mínimo'),
                        ColumnaReporte::numero('Lotes'), ColumnaReporte::texto('Promoción', 1.4),
                        ColumnaReporte::texto('Situación', 1.3),
                    ],
                    $productos->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->categoria->value,
                        (float) $producto->precio, $producto->stock, $producto->stock_minimo,
                        $producto->lotes_count,
                        $producto->promocionVigente() ? ($producto->promocion_titulo ?: 'Destacado') : ($producto->destacado ? 'Programada' : 'Sin promoción'),
                        $this->disponibilidad($producto),
                    ])->all(),
                    vacia: 'Todavía no hay productos en el catálogo.',
                ),
            ],
        );
    }

    private function cotizacion(): Reporte
    {
        $carrito = app(Carrito::class);
        $lineas = $carrito->lineas();

        return $this->reporte(
            'Cotización del pedido',
            'Detalle del pedido en preparación, con los precios vigentes del catálogo.',
            [
                IndicadorReporte::cantidad('Productos', $carrito->productosDistintos(), 'Líneas en el pedido'),
                IndicadorReporte::cantidad('Unidades', $carrito->unidades(), 'Bolsas por despachar'),
                IndicadorReporte::moneda('Subtotal', $carrito->subtotal(), 'Con descuentos aplicados'),
                IndicadorReporte::moneda('Envío', $carrito->envio(), 'Cargo por delivery'),
                IndicadorReporte::moneda('Total', $carrito->total(), 'A pagar al confirmar'),
            ],
            [
                new TablaReporte(
                    'Detalle del pedido',
                    [
                        ColumnaReporte::texto('Producto', 2.6), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.3), ColumnaReporte::moneda('Precio de lista'),
                        ColumnaReporte::moneda('Precio vigente'), ColumnaReporte::numero('Cantidad'),
                        ColumnaReporte::moneda('Importe'),
                    ],
                    $lineas->map(fn (array $linea) => [
                        $linea['producto']->nombre, $linea['producto']->presentacion,
                        $linea['producto']->categoria->value, (float) $linea['producto']->precio,
                        $linea['producto']->precioVigente(), $linea['cantidad'],
                        $linea['producto']->precioVigente() * $linea['cantidad'],
                    ])->all(),
                    vacia: 'El carrito está vacío: agrega productos del catálogo para cotizar.',
                ),
                new TablaReporte(
                    'Resumen del cobro',
                    [ColumnaReporte::texto('Concepto', 3), ColumnaReporte::moneda('Importe')],
                    [
                        ['Subtotal de productos', $carrito->subtotal()],
                        ['Envío', $carrito->envio()],
                        ['Total a pagar', $carrito->total()],
                    ],
                    nota: 'Cotización referencial: los precios se confirman al registrar el pedido.',
                ),
            ],
        );
    }

    private function historial(): Reporte
    {
        $pedidos = $this->pedidosVisibles()->with('lineas')->latest()->latest('id')->get();
        $unidades = $pedidos->sum(fn (Pedido $pedido) => $pedido->unidades());

        return $this->reporte(
            'Reporte de pedidos',
            $this->usuario->rol->veTodosLosPedidos()
                ? 'Historial completo de pedidos con su estado de entrega y de cobro.'
                : 'Historial de los pedidos registrados a nombre de '.$this->usuario->name.'.',
            [
                IndicadorReporte::cantidad('Pedidos', $pedidos->count(), 'En el historial'),
                IndicadorReporte::moneda('Ventas', (float) $pedidos->sum('total'), 'Total facturado'),
                IndicadorReporte::moneda('Por cobrar', (float) $pedidos->filter->cobroPendiente()->sum('total'), 'Cobros todavía abiertos'),
                IndicadorReporte::cantidad('Unidades', $unidades, 'Bolsas pedidas'),
            ],
            [
                new TablaReporte(
                    'Pedidos registrados',
                    [
                        ColumnaReporte::texto('Código', 1.2), ColumnaReporte::fecha('Fecha'),
                        ColumnaReporte::texto('Cliente', 2), ColumnaReporte::texto('Entrega', 1.4),
                        ColumnaReporte::texto('Estado', 1.2), ColumnaReporte::texto('Pago', 1.1),
                        ColumnaReporte::texto('Método', 1.1), ColumnaReporte::numero('Uds', 0.7),
                        ColumnaReporte::moneda('Subtotal'), ColumnaReporte::moneda('Envío', 0.9),
                        ColumnaReporte::moneda('Total'),
                    ],
                    $pedidos->map(fn (Pedido $pedido) => [
                        $pedido->codigo, $pedido->created_at, $pedido->cliente_nombre,
                        $pedido->tipo_entrega->value, $pedido->estado->value, $pedido->estado_pago->value,
                        $pedido->metodo_pago->value, $pedido->unidades(),
                        (float) $pedido->subtotal, (float) $pedido->envio, (float) $pedido->total,
                    ])->all(),
                    vacia: 'Todavía no hay pedidos registrados.',
                ),
                new TablaReporte(
                    'Detalle por producto',
                    [
                        ColumnaReporte::texto('Pedido', 1.2), ColumnaReporte::fecha('Fecha'),
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.2), ColumnaReporte::moneda('Precio'),
                        ColumnaReporte::numero('Cantidad'), ColumnaReporte::moneda('Importe'),
                    ],
                    $pedidos->flatMap(fn (Pedido $pedido) => $pedido->lineas->map(fn ($linea) => [
                        $pedido->codigo, $pedido->created_at, $linea->nombre, $linea->presentacion,
                        $linea->categoria->value, (float) $linea->precio, $linea->cantidad, $linea->importe(),
                    ]))->all(),
                    nota: 'Cada línea conserva el precio con el que se cobró el producto.',
                    vacia: 'Todavía no hay líneas de pedido registradas.',
                ),
            ],
        );
    }

    private function seguimiento(): Reporte
    {
        $pedidos = $this->pedidosVisibles()->with('lineas')->latest()->latest('id')->get();
        $enCurso = $pedidos->whereIn('estado', [EstadoPedido::Pendiente, EstadoPedido::Preparacion]);
        $entregados = $pedidos->where('estado', EstadoPedido::Entregado);

        return $this->reporte(
            'Reporte de seguimiento',
            'Tablero de pedidos: qué está pendiente, qué se está preparando y qué ya se entregó.',
            [
                IndicadorReporte::cantidad('Pendientes', $pedidos->where('estado', EstadoPedido::Pendiente)->count(), 'Esperando preparación'),
                IndicadorReporte::cantidad('En preparación', $pedidos->where('estado', EstadoPedido::Preparacion)->count(), 'Listos para despachar'),
                IndicadorReporte::cantidad('Entregados', $entregados->count(), 'Ciclo cerrado'),
                IndicadorReporte::moneda('Por cobrar', (float) $pedidos->filter->cobroPendiente()->sum('total'), 'Cobros todavía abiertos'),
            ],
            [
                new TablaReporte(
                    'Pedidos en curso',
                    [
                        ColumnaReporte::texto('Código', 1.2), ColumnaReporte::fecha('Registrado'),
                        ColumnaReporte::texto('Cliente', 2), ColumnaReporte::texto('Estado', 1.2),
                        ColumnaReporte::texto('Entrega', 1.3), ColumnaReporte::texto('Dirección', 2.4),
                        ColumnaReporte::numero('Uds', 0.7), ColumnaReporte::moneda('Total'),
                        ColumnaReporte::texto('Pago', 1.1),
                    ],
                    $enCurso->map(fn (Pedido $pedido) => [
                        $pedido->codigo, $pedido->created_at, $pedido->cliente_nombre,
                        $pedido->estado->value, $pedido->tipo_entrega->value,
                        $pedido->cliente_direccion, $pedido->unidades(),
                        (float) $pedido->total, $pedido->estado_pago->value,
                    ])->values()->all(),
                    vacia: 'No hay pedidos pendientes ni en preparación.',
                ),
                new TablaReporte(
                    'Pedidos entregados',
                    [
                        ColumnaReporte::texto('Código', 1.2), ColumnaReporte::fecha('Registrado'),
                        ColumnaReporte::fecha('Entregado'), ColumnaReporte::texto('Cliente', 2),
                        ColumnaReporte::texto('Recibido por', 1.8), ColumnaReporte::numero('Uds', 0.7),
                        ColumnaReporte::moneda('Total'), ColumnaReporte::texto('Pago', 1.1),
                    ],
                    $entregados->map(fn (Pedido $pedido) => [
                        $pedido->codigo, $pedido->created_at, $pedido->entregado_at,
                        $pedido->cliente_nombre, $pedido->entrega_recibido_por,
                        $pedido->unidades(), (float) $pedido->total, $pedido->estado_pago->value,
                    ])->values()->all(),
                    vacia: 'Todavía no se entregó ningún pedido.',
                ),
            ],
        );
    }

    private function lotes(): Reporte
    {
        $lotes = Lote::query()->with('producto')->porVencimiento()->get();
        $conStock = $lotes->where('cantidad_disponible', '>', 0);

        return $this->reporte(
            'Reporte de lotes',
            'Trazabilidad del almacén: qué queda de cada tueste y cuándo vence.',
            [
                IndicadorReporte::cantidad('Lotes registrados', $lotes->count(), 'Historial completo'),
                IndicadorReporte::cantidad('Lotes con stock', $conStock->count(), 'Con unidades disponibles'),
                IndicadorReporte::cantidad('Unidades disponibles', (int) $lotes->sum('cantidad_disponible'), 'Listas para despachar'),
                IndicadorReporte::cantidad('Por vencer', $conStock->filter(fn (Lote $lote) => $lote->porVencer())->count(), 'Dentro de '.Lote::DIAS_AVISO_VENCIMIENTO.' días'),
                IndicadorReporte::cantidad('Unidades de baja', (int) $lotes->sum('cantidad_baja'), 'Merma acumulada'),
            ],
            [
                new TablaReporte(
                    'Inventario por lote',
                    [
                        ColumnaReporte::texto('Lote', 1.2), ColumnaReporte::texto('Producto', 2.2),
                        ColumnaReporte::texto('Presentación', 1.1), ColumnaReporte::fecha('Tostado'),
                        ColumnaReporte::fecha('Vence'), ColumnaReporte::numero('Inicial', 0.9),
                        ColumnaReporte::numero('Disponible', 1.1), ColumnaReporte::numero('De baja', 0.9),
                        ColumnaReporte::texto('Calidad', 1.1), ColumnaReporte::texto('Situación', 1.3),
                    ],
                    $lotes->map(fn (Lote $lote) => [
                        $lote->codigo, $lote->producto->nombre, $lote->producto->presentacion,
                        $lote->tostado_at, $lote->vence_at, $lote->cantidad_inicial,
                        $lote->cantidad_disponible, $lote->cantidad_baja, $lote->calidad->value,
                        $this->situacionDelLote($lote),
                    ])->all(),
                    nota: 'Ordenado por vencimiento: así se despacha, primero lo que vence antes.',
                    vacia: 'Todavía no se registró ningún lote.',
                ),
                new TablaReporte(
                    'Resumen por producto',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::numero('Lotes'), ColumnaReporte::numero('Unidades disponibles', 1.4),
                        ColumnaReporte::numero('Unidades de baja', 1.3),
                    ],
                    $lotes->groupBy('producto_id')->map(fn (Collection $grupo) => [
                        $grupo->first()->producto->nombre,
                        $grupo->first()->producto->presentacion,
                        $grupo->count(),
                        (int) $grupo->sum('cantidad_disponible'),
                        (int) $grupo->sum('cantidad_baja'),
                    ])->sortBy(fn (array $fila) => $fila[0])->values()->all(),
                    vacia: 'Todavía no se registró ningún lote.',
                ),
            ],
        );
    }

    private function calidad(): Reporte
    {
        $lotes = Lote::query()->with('producto', 'evaluador')->porVencimiento()->get();
        $pendientes = $lotes->where('calidad', ResultadoCalidad::Pendiente);
        $evaluados = $lotes->filter->evaluado()->sortByDesc('evaluado_at');

        return $this->reporte(
            'Reporte de control de calidad',
            'Resultado del control de cada lote y unidades bloqueadas para la venta.',
            [
                IndicadorReporte::cantidad('Lotes por evaluar', $pendientes->count(), 'Sin control registrado'),
                IndicadorReporte::cantidad('Aprobados', $lotes->where('calidad', ResultadoCalidad::Aprobado)->count(), 'Aptos para la venta'),
                IndicadorReporte::cantidad('Rechazados', $lotes->where('calidad', ResultadoCalidad::Rechazado)->count(), 'Bloqueados para la venta'),
                IndicadorReporte::cantidad('Unidades bloqueadas', (int) $lotes->filter->bloqueado()->sum('cantidad_disponible'), 'Fuera del despacho'),
            ],
            [
                $this->tablaDeLotes(
                    'Lotes pendientes de control',
                    $pendientes->values(),
                    'Lotes tostados que todavía esperan su resultado de calidad.',
                    'No quedan lotes por evaluar.',
                ),
                new TablaReporte(
                    'Lotes evaluados',
                    [
                        ColumnaReporte::texto('Lote', 1.1), ColumnaReporte::texto('Producto', 2),
                        ColumnaReporte::texto('Resultado', 1.1), ColumnaReporte::fecha('Evaluado'),
                        ColumnaReporte::texto('Evaluado por', 1.6), ColumnaReporte::numero('Disponible', 1.1),
                        ColumnaReporte::texto('Observación', 2.6),
                    ],
                    $evaluados->map(fn (Lote $lote) => [
                        $lote->codigo, $lote->producto->nombre, $lote->calidad->value,
                        $lote->evaluado_at, $lote->evaluador?->name, $lote->cantidad_disponible,
                        $lote->calidad_nota,
                    ])->values()->all(),
                    vacia: 'Todavía no se registró ningún control de calidad.',
                ),
            ],
        );
    }

    private function promociones(): Reporte
    {
        $productos = Producto::query()
            ->withSum('lineas as unidades_vendidas', 'cantidad')
            ->orderBy('nombre')
            ->get();

        $vigentes = $productos->filter->promocionVigente();
        $programadas = $productos->filter(fn (Producto $producto) => $producto->destacado && ! $producto->promocionVigente());
        $descuento = (new ResumenComercial)->descuentoEntregado();

        return $this->reporte(
            'Reporte de promociones',
            'Qué productos se destacan, con qué descuento y durante qué fechas.',
            [
                IndicadorReporte::cantidad('Promociones vigentes', $vigentes->count(), 'Anunciadas hoy en el catálogo'),
                IndicadorReporte::cantidad('Programadas o vencidas', $programadas->count(), 'Destacadas fuera de fecha'),
                IndicadorReporte::cantidad('Sin promoción', $productos->where('destacado', false)->count(), 'Resto del catálogo'),
                IndicadorReporte::moneda('Descuento entregado', $descuento['descuento'], $descuento['unidades'].' uds vendidas en promoción'),
            ],
            [
                $this->tablaDePromociones($vigentes->values()),
                new TablaReporte(
                    'Promociones programadas o vencidas',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Título', 2.2), ColumnaReporte::porcentaje('Descuento'),
                        ColumnaReporte::fecha('Inicia'), ColumnaReporte::fecha('Termina'),
                        ColumnaReporte::texto('Situación', 1.4),
                    ],
                    $programadas->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->promocion_titulo,
                        $producto->descuento ?: null, $producto->promocion_inicia_at, $producto->promocion_termina_at,
                        ($producto->promocion_inicia_at?->isFuture() ?? false) ? 'Programada' : 'Vencida',
                    ])->values()->all(),
                    vacia: 'No hay promociones fuera de fecha.',
                ),
                new TablaReporte(
                    'Catálogo sin promoción',
                    [
                        ColumnaReporte::texto('Producto', 2.4), ColumnaReporte::texto('Presentación', 1.2),
                        ColumnaReporte::texto('Categoría', 1.3), ColumnaReporte::moneda('Precio'),
                        ColumnaReporte::numero('Stock'), ColumnaReporte::numero('Unidades vendidas', 1.4),
                    ],
                    $productos->where('destacado', false)->map(fn (Producto $producto) => [
                        $producto->nombre, $producto->presentacion, $producto->categoria->value,
                        (float) $producto->precio, $producto->stock, (int) $producto->unidades_vendidas,
                    ])->values()->all(),
                    nota: 'Candidatos a destacar en la próxima campaña.',
                    vacia: 'Todo el catálogo tiene alguna promoción cargada.',
                ),
            ],
        );
    }

    private function usuarios(): Reporte
    {
        $usuarios = User::query()->withCount('pedidos')->orderBy('rol')->orderBy('name')->get();

        return $this->reporte(
            'Reporte de usuarios',
            'Cuentas del sistema, el rol con el que entran y su actividad.',
            [
                IndicadorReporte::cantidad('Cuentas', $usuarios->count(), 'Registradas en el sistema'),
                IndicadorReporte::cantidad('Activas', $usuarios->where('activo', true)->count(), 'Pueden iniciar sesión'),
                IndicadorReporte::cantidad('Desactivadas', $usuarios->where('activo', false)->count(), 'Sin acceso'),
                IndicadorReporte::cantidad('Roles con cuenta', $usuarios->pluck('rol')->unique()->count(), 'De '.count(Rol::cases()).' roles definidos'),
            ],
            [
                new TablaReporte(
                    'Cuentas registradas',
                    [
                        ColumnaReporte::texto('Usuario', 1.3), ColumnaReporte::texto('Nombre', 2),
                        ColumnaReporte::texto('Correo', 2.4), ColumnaReporte::texto('Rol', 1.8),
                        ColumnaReporte::texto('Estado', 1), ColumnaReporte::numero('Pedidos', 0.9),
                        ColumnaReporte::fecha('Alta'),
                    ],
                    $usuarios->map(fn (User $usuario) => [
                        $usuario->username, $usuario->name, $usuario->email, $usuario->rol->value,
                        $usuario->activo ? 'Activa' : 'Desactivada', $usuario->pedidos_count,
                        $usuario->created_at,
                    ])->all(),
                ),
                new TablaReporte(
                    'Cuentas por rol',
                    [
                        ColumnaReporte::texto('Rol', 1.8), ColumnaReporte::numero('Cuentas', 0.9),
                        ColumnaReporte::numero('Activas', 0.9), ColumnaReporte::texto('Responsabilidad', 4),
                    ],
                    collect(Rol::cases())->map(fn (Rol $rol) => [
                        $rol->value,
                        $usuarios->where('rol', $rol)->count(),
                        $usuarios->where('rol', $rol)->where('activo', true)->count(),
                        $rol->proposito(),
                    ])->all(),
                ),
            ],
        );
    }

    /**
     * @param  list<IndicadorReporte>  $indicadores
     * @param  list<TablaReporte>  $tablas
     */
    private function reporte(string $titulo, string $subtitulo, array $indicadores, array $tablas): Reporte
    {
        return new Reporte(
            titulo: $titulo,
            subtitulo: $subtitulo,
            area: $this->usuario->rol->value,
            indicadores: $indicadores,
            tablas: $tablas,
            generadoPor: $this->usuario->name,
        );
    }

    private function tablaDeVentasPorTramo(ResumenIndicadores $resumen, PeriodoReporte $periodo): TablaReporte
    {
        return new TablaReporte(
            'Ventas por periodo',
            [ColumnaReporte::texto('Tramo', 2), ColumnaReporte::numero('Pedidos'), ColumnaReporte::moneda('Ventas')],
            $resumen->ventasPorTramo()
                ->map(fn (array $tramo) => [$tramo['etiqueta'], $tramo['pedidos'], $tramo['total']])
                ->all(),
            nota: $periodo->titulo().' · últimos '.$periodo->tramos().' tramos.',
        );
    }

    private function tablaDeMasVendidos(ResumenIndicadores $resumen): TablaReporte
    {
        return new TablaReporte(
            'Productos más vendidos',
            [
                ColumnaReporte::texto('Producto', 2.6), ColumnaReporte::texto('Presentación', 1.2),
                ColumnaReporte::numero('Unidades'), ColumnaReporte::moneda('Importe'),
            ],
            $resumen->productosMasVendidos(15)
                ->map(fn (array $fila) => [$fila['nombre'], $fila['presentacion'], $fila['unidades'], $fila['importe']])
                ->all(),
            vacia: 'Todavía no hay productos vendidos.',
        );
    }

    /**
     * @param  Collection<int, Producto>  $productos
     */
    private function tablaDePromociones(Collection $productos): TablaReporte
    {
        return new TablaReporte(
            'Promociones vigentes',
            [
                ColumnaReporte::texto('Producto', 2.2), ColumnaReporte::texto('Presentación', 1.1),
                ColumnaReporte::texto('Título', 2), ColumnaReporte::porcentaje('Descuento'),
                ColumnaReporte::moneda('Precio de lista'), ColumnaReporte::moneda('Precio vigente'),
                ColumnaReporte::moneda('Ahorro', 1), ColumnaReporte::fecha('Termina'),
                ColumnaReporte::numero('Uds vendidas', 1.2),
            ],
            $productos->map(fn (Producto $producto) => [
                $producto->nombre, $producto->presentacion, $producto->promocion_titulo,
                $producto->descuento ?: null, (float) $producto->precio, $producto->precioVigente(),
                $producto->ahorro() ?: null, $producto->promocion_termina_at,
                isset($producto->unidades_vendidas) ? (int) $producto->unidades_vendidas : null,
            ])->values()->all(),
            vacia: 'No hay promociones vigentes hoy.',
        );
    }

    /**
     * @param  Collection<int, Lote>  $lotes
     */
    private function tablaDeLotes(string $titulo, Collection $lotes, ?string $nota, string $vacia): TablaReporte
    {
        return new TablaReporte(
            $titulo,
            [
                ColumnaReporte::texto('Lote', 1.2), ColumnaReporte::texto('Producto', 2.2),
                ColumnaReporte::texto('Presentación', 1.1), ColumnaReporte::fecha('Tostado'),
                ColumnaReporte::fecha('Vence'), ColumnaReporte::numero('Días', 0.8),
                ColumnaReporte::numero('Inicial', 0.9), ColumnaReporte::numero('Disponible', 1.1),
                ColumnaReporte::texto('Calidad', 1.1),
            ],
            $lotes->map(fn (Lote $lote) => [
                $lote->codigo, $lote->producto->nombre, $lote->producto->presentacion,
                $lote->tostado_at, $lote->vence_at,
                $lote->vencido() ? -$lote->diasParaVencer() : $lote->diasParaVencer(),
                $lote->cantidad_inicial, $lote->cantidad_disponible, $lote->calidad->value,
            ])->values()->all(),
            nota: $nota,
            vacia: $vacia,
        );
    }

    /** Pedidos que el rol tiene permitido ver: el cliente solo los suyos. */
    private function pedidosVisibles(): Builder
    {
        return Pedido::query()->unless(
            $this->usuario->rol->veTodosLosPedidos(),
            fn (Builder $query) => $query->whereBelongsTo($this->usuario, 'usuario'),
        );
    }

    private function disponibilidad(Producto $producto): string
    {
        return match (true) {
            $producto->agotado() => 'Agotado',
            $producto->bajoStock() => 'En el stock mínimo',
            default => 'Disponible',
        };
    }

    private function situacionDelLote(Lote $lote): string
    {
        return match (true) {
            $lote->bloqueado() => 'Bloqueado por calidad',
            $lote->agotado() => 'Agotado',
            $lote->vencido() => 'Vencido',
            $lote->porVencer() => 'Por vencer',
            default => 'Disponible',
        };
    }

    private function periodo(PeriodoReporte $porDefecto): PeriodoReporte
    {
        return PeriodoReporte::tryFrom($this->texto('periodo')) ?? $porDefecto;
    }

    private function texto(string $clave): string
    {
        $valor = $this->parametros[$clave] ?? '';

        return is_string($valor) ? trim($valor) : '';
    }
}
