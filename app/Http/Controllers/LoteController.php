<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoteRequest;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoteController extends Controller
{
    /**
     * Almacén por lotes: qué queda de cada tueste y qué está por vencer.
     */
    public function index(): View
    {
        $productos = Producto::query()
            ->with(['lotes' => fn ($query) => $query->porVencimiento()])
            ->orderBy('nombre')
            ->get();

        $lotes = $productos->flatMap->lotes;

        return view('lotes.index', [
            'productos' => $productos,
            'unidadesDisponibles' => (int) $lotes->sum('cantidad_disponible'),
            'lotesActivos' => $lotes->where('cantidad_disponible', '>', 0)->count(),
            'lotesPorVencer' => $lotes->filter(fn (Lote $lote) => ! $lote->agotado() && $lote->porVencer())->count(),
        ]);
    }

    /**
     * Registra la entrada de un lote nuevo y actualiza el stock del producto.
     */
    public function store(StoreLoteRequest $request): RedirectResponse
    {
        $producto = Producto::where('slug', $request->string('producto'))->firstOrFail();

        $lote = $producto->lotes()->create([
            'codigo' => $request->string('codigo')->upper()->toString(),
            'cantidad_inicial' => $request->integer('cantidad'),
            'cantidad_disponible' => $request->integer('cantidad'),
            'tostado_at' => $request->date('tostado_at'),
            'vence_at' => $request->date('vence_at'),
        ]);

        $producto->sincronizarStock();

        return back()->with('aviso', "Lote {$lote->codigo} registrado con {$lote->cantidad_inicial} uds de {$producto->nombre}.");
    }
}
