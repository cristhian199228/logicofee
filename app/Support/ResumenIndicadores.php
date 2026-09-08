<?php

namespace App\Support;

use App\Enums\EstadoPedido;
use App\Enums\PeriodoReporte;
use App\Enums\ResultadoCalidad;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicadores del panel de gerencia: pedidos por estado, ventas por periodo y
 * productos más vendidos (HU08).
 */
class ResumenIndicadores
{
    public function __construct(private PeriodoReporte $periodo) {}

    /**
     * Cuántos pedidos y cuánto dinero hay en cada etapa del flujo.
     *
     * @return Collection<int, array{estado: EstadoPedido, cantidad: int, total: float}>
     */
    public function pedidosPorEstado(): Collection
    {
        $totales = Pedido::query()
            ->selectRaw('estado, COUNT(*) as cantidad, SUM(total) as importe')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        return collect(EstadoPedido::cases())->map(fn (EstadoPedido $estado) => [
            'estado' => $estado,
            'cantidad' => (int) ($totales->get($estado->value)?->cantidad ?? 0),
            'total' => (float) ($totales->get($estado->value)?->importe ?? 0),
        ]);
    }

    public function totalPedidos(): int
    {
        return Pedido::query()->count();
    }

    public function ventasTotales(): float
    {
        return (float) Pedido::query()->sum('total');
    }

    public function ticketPromedio(): float
    {
        $pedidos = $this->totalPedidos();

        return $pedidos === 0 ? 0.0 : round($this->ventasTotales() / $pedidos, 2);
    }

    public function unidadesVendidas(): int
    {
        return (int) PedidoLinea::query()->sum('cantidad');
    }

    /**
     * Ventas de los últimos tramos del periodo elegido (día, semana o mes).
     * Los tramos sin pedidos aparecen en cero para que la gráfica no salte.
     *
     * @return Collection<int, array{etiqueta: string, total: float, pedidos: int}>
     */
    public function ventasPorTramo(): Collection
    {
        $tramos = $this->tramosVacios();

        $pedidos = Pedido::query()
            ->where('created_at', '>=', array_key_first($tramos))
            ->get(['created_at', 'total']);

        foreach ($pedidos as $pedido) {
            $clave = $this->periodo->inicioDelTramo($pedido->created_at)->toDateString();

            if (! isset($tramos[$clave])) {
                continue;
            }

            $tramos[$clave]['total'] += (float) $pedido->total;
            $tramos[$clave]['pedidos']++;
        }

        return collect($tramos)->values();
    }

    /**
     * Productos con más unidades despachadas, con el importe que generaron.
     *
     * @return Collection<int, array{nombre: string, presentacion: string, unidades: int, importe: float}>
     */
    public function productosMasVendidos(int $limite = 5): Collection
    {
        return PedidoLinea::query()
            ->selectRaw('nombre, presentacion, SUM(cantidad) as unidades, SUM(precio * cantidad) as importe')
            ->groupBy('nombre', 'presentacion')
            ->orderByDesc('unidades')
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(fn (PedidoLinea $linea) => [
                'nombre' => $linea->nombre,
                'presentacion' => $linea->presentacion,
                'unidades' => (int) $linea->unidades,
                'importe' => (float) $linea->importe,
            ]);
    }

    /**
     * Productos agotados o por debajo del stock mínimo (HU05).
     *
     * @return Collection<int, Producto>
     */
    public function alertasDeStock(): Collection
    {
        return Producto::query()
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->orderBy('nombre')
            ->get();
    }

    /** Dinero de pedidos con el cobro todavía abierto. */
    public function porCobrar(): float
    {
        return (float) Pedido::query()->porCobrar()->sum('total');
    }

    /** Lotes bloqueados por el control de calidad (HU07). */
    public function lotesBloqueados(): int
    {
        return Lote::query()->where('calidad', ResultadoCalidad::Rechazado->value)->count();
    }

    /** Lotes con unidades todavía sin control de calidad. */
    public function lotesSinEvaluar(): int
    {
        return Lote::query()->sinEvaluar()->disponibles()->count();
    }

    public function periodo(): PeriodoReporte
    {
        return $this->periodo;
    }

    /**
     * Tramos del periodo, del más antiguo al actual, con los importes en cero.
     *
     * @return array<string, array{etiqueta: string, total: float, pedidos: int}>
     */
    private function tramosVacios(): array
    {
        $actual = $this->periodo->inicioDelTramo(Carbon::now());
        $tramos = [];

        foreach (range($this->periodo->tramos() - 1, 0) as $atras) {
            $inicio = $this->periodo->retroceder($actual, $atras);

            $tramos[$inicio->toDateString()] = [
                'etiqueta' => $this->periodo->etiquetaDelTramo($inicio),
                'total' => 0.0,
                'pedidos' => 0,
            ];
        }

        return $tramos;
    }
}
