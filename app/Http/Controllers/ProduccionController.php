<?php

namespace App\Http\Controllers;

use App\Support\PlanProduccion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del área de Producción y Operaciones: qué toca tostar, qué lotes
 * esperan control de calidad y cómo viene el rendimiento.
 */
class ProduccionController extends Controller
{
    public function index(Request $request, PlanProduccion $plan): View
    {
        return view('produccion.index', [
            'ordenes' => $plan->ordenesSugeridas(),
            'unidadesSugeridas' => $plan->unidadesSugeridas(),
            'pendientes' => $plan->lotesPendientes(),
            'ultimosLotes' => $plan->ultimosLotes(),
            'unidadesProducidas' => $plan->unidadesProducidas(),
            'rendimiento' => $plan->rendimientoCalidad(),
            'unidadesBloqueadas' => $plan->unidadesBloqueadas(),
            'porcentajeDeMerma' => $plan->porcentajeDeMerma(),
            'puedeControlarCalidad' => $request->user()->rol->puedeControlarCalidad(),
            'puedeMoverAlmacen' => $request->user()->rol->puedeMoverAlmacen(),
        ]);
    }
}
