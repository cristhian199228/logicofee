<?php

namespace App\Http\Controllers;

use App\Enums\ResultadoCalidad;
use App\Http\Requests\StoreControlCalidadRequest;
use App\Models\Lote;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Control de calidad de los lotes de producción (HU07).
 */
class ControlCalidadController extends Controller
{
    public function index(): View
    {
        $lotes = Lote::query()
            ->with('producto', 'evaluador')
            ->porVencimiento()
            ->get();

        return view('calidad.index', [
            'pendientes' => $lotes->where('calidad', ResultadoCalidad::Pendiente),
            'evaluados' => $lotes->filter->evaluado()->sortByDesc('evaluado_at'),
            'conteos' => collect(ResultadoCalidad::cases())->mapWithKeys(
                fn (ResultadoCalidad $resultado) => [$resultado->value => $lotes->where('calidad', $resultado)->count()]
            ),
            'unidadesBloqueadas' => (int) $lotes->filter->bloqueado()->sum('cantidad_disponible'),
        ]);
    }

    /**
     * Registra el resultado del control: el lote rechazado queda bloqueado
     * para la venta y deja de sumar al stock del producto.
     */
    public function store(StoreControlCalidadRequest $request, Lote $lote): RedirectResponse
    {
        $resultado = ResultadoCalidad::from($request->string('resultado')->toString());

        $lote->evaluar($resultado, $request->string('calidad_nota')->trim()->value() ?: null, $request->user());

        return back()->with('aviso', $resultado->bloqueaVenta()
            ? "Lote {$lote->codigo} rechazado: sus {$lote->cantidad_disponible} uds quedaron bloqueadas para la venta."
            : "Lote {$lote->codigo} aprobado para la venta.");
    }
}
