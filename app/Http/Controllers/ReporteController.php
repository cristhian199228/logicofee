<?php

namespace App\Http\Controllers;

use App\Enums\PeriodoReporte;
use App\Support\ResumenIndicadores;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel de indicadores de gerencia (HU08).
 */
class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        $periodo = PeriodoReporte::tryFrom($request->string('periodo')->toString()) ?? PeriodoReporte::Dia;
        $resumen = new ResumenIndicadores($periodo);
        $ventas = $resumen->ventasPorTramo();

        return view('reportes.index', [
            'periodo' => $periodo,
            'porEstado' => $resumen->pedidosPorEstado(),
            'totalPedidos' => $resumen->totalPedidos(),
            'ventasTotales' => $resumen->ventasTotales(),
            'porCobrar' => $resumen->porCobrar(),
            'ticketPromedio' => $resumen->ticketPromedio(),
            'unidadesVendidas' => $resumen->unidadesVendidas(),
            'ventas' => $ventas,
            'ventaMaxima' => (float) $ventas->max('total'),
            'masVendidos' => $resumen->productosMasVendidos(),
            'alertasDeStock' => $resumen->alertasDeStock(),
            'lotesBloqueados' => $resumen->lotesBloqueados(),
            'lotesSinEvaluar' => $resumen->lotesSinEvaluar(),
        ]);
    }
}
