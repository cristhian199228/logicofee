<?php

namespace App\Support;

use App\Enums\EstadoPago;
use App\Enums\MetodoPago;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del área de Marketing y Ventas: quién compra, qué tipo de
 * cliente deja más dinero y qué está rindiendo el catálogo en promoción.
 */
class ResumenComercial
{
    /**
     * Clientes que más han comprado, por importe acumulado.
     *
     * @return Collection<int, array{nombre: string, tipo: string, pedidos: int, total: float, ultima: string}>
     */
    public function mejoresClientes(int $limite = 5): Collection
    {
        return DB::table('pedidos')
            ->selectRaw('cliente_nombre, MIN(cliente_tipo) as cliente_tipo, COUNT(*) as pedidos, SUM(total) as importe, MAX(created_at) as ultima')
            ->groupBy('cliente_nombre')
            ->orderByDesc('importe')
            ->orderBy('cliente_nombre')
            ->limit($limite)
            ->get()
            ->map(fn (object $fila) => [
                'nombre' => $fila->cliente_nombre,
                'tipo' => $fila->cliente_tipo,
                'pedidos' => (int) $fila->pedidos,
                'total' => (float) $fila->importe,
                'ultima' => (string) $fila->ultima,
            ]);
    }

    /**
     * Reparto de las ventas entre los tipos de cliente (cafetería, bodega...).
     *
     * @return Collection<int, array{tipo: string, pedidos: int, total: float}>
     */
    public function ventasPorTipoDeCliente(): Collection
    {
        return DB::table('pedidos')
            ->selectRaw('cliente_tipo, COUNT(*) as pedidos, SUM(total) as importe')
            ->groupBy('cliente_tipo')
            ->orderByDesc('importe')
            ->get()
            ->map(fn (object $fila) => [
                'tipo' => $fila->cliente_tipo,
                'pedidos' => (int) $fila->pedidos,
                'total' => (float) $fila->importe,
            ]);
    }

    /**
     * Promociones vigentes hoy con las unidades que ya movieron (HU03).
     *
     * @return Collection<int, Producto>
     */
    public function promocionesVigentes(): Collection
    {
        return Producto::query()
            ->enPromocion()
            ->withSum('lineas as unidades_vendidas', 'cantidad')
            ->orderByDesc('descuento')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Catálogo sin una sola venta registrada: candidatos a promoción.
     *
     * @return Collection<int, Producto>
     */
    public function productosSinVentas(): Collection
    {
        return Producto::query()
            ->whereDoesntHave('lineas')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Cuánto descuento se entregó y sobre cuántas unidades: la línea del
     * pedido guarda el precio cobrado, así que se compara con el de lista.
     *
     * @return array{descuento: float, unidades: int}
     */
    public function descuentoEntregado(): array
    {
        $fila = PedidoLinea::query()
            ->join('productos', 'productos.id', '=', 'pedido_lineas.producto_id')
            ->whereColumn('pedido_lineas.precio', '<', 'productos.precio')
            ->selectRaw('SUM((productos.precio - pedido_lineas.precio) * pedido_lineas.cantidad) as descuento, SUM(pedido_lineas.cantidad) as unidades')
            ->toBase()
            ->first();

        return [
            'descuento' => (float) ($fila->descuento ?? 0),
            'unidades' => (int) ($fila->unidades ?? 0),
        ];
    }

    /**
     * Cuánto entró por cada forma de pago y cuánto de eso sigue por cobrar.
     *
     * @return Collection<int, array{metodo: MetodoPago, pedidos: int, total: float, porCobrar: float}>
     */
    public function ventasPorMetodoDePago(): Collection
    {
        $totales = DB::table('pedidos')
            ->selectRaw('metodo_pago, COUNT(*) as pedidos, SUM(total) as importe, SUM(CASE WHEN estado_pago = ? THEN total ELSE 0 END) as cobrado', [EstadoPago::Pagado->value])
            ->groupBy('metodo_pago')
            ->get()
            ->keyBy('metodo_pago');

        return collect(MetodoPago::cases())->map(function (MetodoPago $metodo) use ($totales) {
            $fila = $totales->get($metodo->value);

            return [
                'metodo' => $metodo,
                'pedidos' => (int) ($fila->pedidos ?? 0),
                'total' => (float) ($fila->importe ?? 0),
                'porCobrar' => (float) ($fila->importe ?? 0) - (float) ($fila->cobrado ?? 0),
            ];
        });
    }

    /** Dinero de pedidos cuyo cobro todavía no se cerró. */
    public function porCobrar(): float
    {
        return (float) Pedido::query()->porCobrar()->sum('total');
    }

    /** Clientes distintos que ya compraron al menos una vez. */
    public function clientesAtendidos(): int
    {
        return (int) DB::table('pedidos')->distinct()->count('cliente_nombre');
    }
}
