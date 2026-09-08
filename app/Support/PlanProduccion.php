<?php

namespace App\Support;

use App\Enums\ResultadoCalidad;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Plan del área de Producción y Operaciones: qué toca tostar, qué lotes
 * esperan control de calidad y cómo viene el rendimiento de la producción.
 */
class PlanProduccion
{
    /** Ventana en días sobre la que se mide la producción reciente. */
    public const DIAS_DE_PRODUCCION = 30;

    /**
     * Órdenes de tueste sugeridas: los productos en el mínimo o agotados, con
     * las unidades que hacen falta para dejarlos al doble de su stock mínimo.
     *
     * @return Collection<int, array{producto: Producto, sugerido: int, urgente: bool}>
     */
    public function ordenesSugeridas(): Collection
    {
        return Producto::query()
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Producto $producto) => [
                'producto' => $producto,
                'sugerido' => max($producto->stock_minimo * 2 - $producto->stock, 1),
                'urgente' => $producto->agotado(),
            ]);
    }

    /** Unidades sugeridas en total, para dimensionar el turno de tueste. */
    public function unidadesSugeridas(): int
    {
        return (int) $this->ordenesSugeridas()->sum('sugerido');
    }

    /**
     * Lotes tostados que todavía no pasaron por control de calidad (HU07).
     *
     * @return Collection<int, Lote>
     */
    public function lotesPendientes(): Collection
    {
        return Lote::query()
            ->with('producto')
            ->sinEvaluar()
            ->disponibles()
            ->porVencimiento()
            ->get();
    }

    /**
     * Últimos lotes tostados, para seguir lo que salió del horno.
     *
     * @return Collection<int, Lote>
     */
    public function ultimosLotes(int $limite = 6): Collection
    {
        return Lote::query()
            ->with('producto', 'evaluador')
            ->orderByDesc('tostado_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /** Unidades tostadas dentro de la ventana de producción. */
    public function unidadesProducidas(): int
    {
        return (int) Lote::query()
            ->whereDate('tostado_at', '>=', now()->subDays(self::DIAS_DE_PRODUCCION))
            ->sum('cantidad_inicial');
    }

    /**
     * Rendimiento del control de calidad sobre los lotes ya evaluados (HU07).
     *
     * @return array{aprobados: int, rechazados: int, pendientes: int, porcentaje: int}
     */
    public function rendimientoCalidad(): array
    {
        $conteos = Lote::query()
            ->selectRaw('calidad, COUNT(*) as cantidad')
            ->groupBy('calidad')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila) => [$fila->calidad => (int) $fila->cantidad]);

        $aprobados = $conteos->get(ResultadoCalidad::Aprobado->value, 0);
        $rechazados = $conteos->get(ResultadoCalidad::Rechazado->value, 0);
        $evaluados = $aprobados + $rechazados;

        return [
            'aprobados' => $aprobados,
            'rechazados' => $rechazados,
            'pendientes' => $conteos->get(ResultadoCalidad::Pendiente->value, 0),
            'porcentaje' => $evaluados === 0 ? 0 : (int) round($aprobados / $evaluados * 100),
        ];
    }

    /** Unidades que el control de calidad dejó fuera de la venta (HU07). */
    public function unidadesBloqueadas(): int
    {
        return (int) Lote::query()
            ->where('calidad', ResultadoCalidad::Rechazado->value)
            ->sum('cantidad_disponible');
    }

    /**
     * Merma sobre lo producido: qué porcentaje de las unidades tostadas
     * terminó dado de baja en el almacén.
     */
    public function porcentajeDeMerma(): float
    {
        $producidas = (int) Lote::query()->sum('cantidad_inicial');

        if ($producidas === 0) {
            return 0.0;
        }

        return round((int) Lote::query()->sum('cantidad_baja') / $producidas * 100, 1);
    }
}
