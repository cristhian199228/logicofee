<?php

namespace App\Http\Controllers;

use App\Support\ResumenAlmacen;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del área de Logística y Almacén: stock por lote, vencimientos,
 * mermas y la reposición que toca pedir a producción.
 */
class AlmacenController extends Controller
{
    public function index(Request $request, ResumenAlmacen $almacen): View
    {
        return view('almacen.index', [
            'unidadesDisponibles' => $almacen->unidadesDisponibles(),
            'valorInventario' => $almacen->valorInventario(),
            'lotesActivos' => $almacen->lotesActivos(),
            'unidadesMermadas' => $almacen->unidadesMermadas(),
            'pedidosPorDespachar' => $almacen->pedidosPorDespachar(),
            'porVencer' => $almacen->lotesPorVencer(),
            'vencidos' => $almacen->lotesVencidos(),
            'reposicion' => $almacen->reposicionSugerida(),
            'cobertura' => $almacen->coberturaPorProducto(),
            'puedeMoverAlmacen' => $request->user()->rol->puedeMoverAlmacen(),
        ]);
    }
}
