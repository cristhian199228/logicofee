<?php

namespace App\Support;

use App\Enums\EstadoPedido;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del área de Logística y Almacén: qué hay en stock, qué está por
 * vencer, qué se perdió por merma y qué toca reponer.
 */
class ResumenAlmacen
{
    /** Ventana en días con la que se estima el consumo diario. */
    public const DIAS_DE_CONSUMO = 30;

    public function unidadesDisponibles(): int
    {
        return (int) Lote::query()->vendibles()->sum('cantidad_disponible');
    }

    /** Valor del inventario a precio de lista de cada producto. */
    public function valorInventario(): float
    {
        return (float) Producto::query()->sum(DB::raw('stock * precio'));
    }

    public function lotesActivos(): int
    {
        return Lote::query()->disponibles()->count();
    }

    public function unidadesMermadas(): int
    {
        return (int) Lote::query()->sum('cantidad_baja');
    }

    /** Pedidos que todavía esperan preparación o entrega (HU03). */
    public function pedidosPorDespachar(): int
    {
        return Pedido::query()
            ->whereIn('estado', [EstadoPedido::Pendiente->value, EstadoPedido::Preparacion->value])
            ->count();
    }

    /**
     * Lotes con unidades que vencen dentro de la ventana de aviso.
     *
     * @return Collection<int, Lote>
     */
    public function lotesPorVencer(): Collection
    {
        return Lote::query()
            ->with('producto')
            ->disponibles()
            ->whereDate('vence_at', '>=', now())
            ->whereDate('vence_at', '<=', now()->addDays(Lote::DIAS_AVISO_VENCIMIENTO))
            ->porVencimiento()
            ->get();
    }

    /**
     * Lotes vencidos que siguen ocupando unidades: candidatos a baja.
     *
     * @return Collection<int, Lote>
     */
    public function lotesVencidos(): Collection
    {
        return Lote::query()
            ->with('producto')
            ->disponibles()
            ->vencidos()
            ->porVencimiento()
            ->get();
    }

    /**
     * Reposición sugerida: los productos en el mínimo o agotados, con las
     * unidades que faltan para dejarlos al doble de su stock mínimo.
     *
     * @return Collection<int, array{producto: Producto, sugerido: int, dias: float|null}>
     */
    public function reposicionSugerida(): Collection
    {
        $consumo = $this->consumoDiarioPorProducto();

        return Producto::query()
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Producto $producto) => [
                'producto' => $producto,
                'sugerido' => max($producto->stock_minimo * 2 - $producto->stock, 1),
                'dias' => $this->diasDeCobertura($producto, $consumo->get($producto->id, 0.0)),
            ]);
    }

    /**
     * Días de stock que le quedan a cada producto al ritmo de venta reciente.
     *
     * @return Collection<int, array{producto: Producto, consumo: float, dias: float|null}>
     */
    public function coberturaPorProducto(): Collection
    {
        $consumo = $this->consumoDiarioPorProducto();

        return Producto::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Producto $producto) => [
                'producto' => $producto,
                'consumo' => round($consumo->get($producto->id, 0.0), 2),
                'dias' => $this->diasDeCobertura($producto, $consumo->get($producto->id, 0.0)),
            ])
            ->sortBy(fn (array $fila) => $fila['dias'] ?? INF)
            ->values();
    }

    /**
     * Unidades que sale cada producto por día, según lo vendido en la ventana.
     *
     * @return Collection<int, float>
     */
    private function consumoDiarioPorProducto(): Collection
    {
        return PedidoLinea::query()
            ->whereNotNull('producto_id')
            ->where('created_at', '>=', now()->subDays(self::DIAS_DE_CONSUMO))
            ->selectRaw('producto_id, SUM(cantidad) as unidades')
            ->groupBy('producto_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila) => [
                (int) $fila->producto_id => (float) $fila->unidades / self::DIAS_DE_CONSUMO,
            ]);
    }

    /** Sin consumo reciente no hay cobertura que estimar. */
    private function diasDeCobertura(Producto $producto, float $consumoDiario): ?float
    {
        return $consumoDiario > 0 ? round($producto->stock / $consumoDiario, 1) : null;
    }
}
