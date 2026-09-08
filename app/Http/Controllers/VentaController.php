<?php

namespace App\Http\Controllers;

use App\Enums\PeriodoReporte;
use App\Support\ResumenComercial;
use App\Support\ResumenIndicadores;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del área de Marketing y Ventas: cómo evolucionan las ventas, quién
 * compra y qué está rindiendo el catálogo en promoción.
 */
class VentaController extends Controller
{
    public function index(Request $request, ResumenComercial $comercial): View
    {
        $periodo = PeriodoReporte::tryFrom($request->string('periodo')->toString()) ?? PeriodoReporte::Semana;
        $indicadores = new ResumenIndicadores($periodo);
        $ventas = $indicadores->ventasPorTramo();

        return view('ventas.index', [
            'periodo' => $periodo,
            'ventas' => $ventas,
            'ventaMaxima' => (float) $ventas->max('total'),
            'ventasTotales' => $indicadores->ventasTotales(),
            'totalPedidos' => $indicadores->totalPedidos(),
            'ticketPromedio' => $indicadores->ticketPromedio(),
            'unidadesVendidas' => $indicadores->unidadesVendidas(),
            'porEstado' => $indicadores->pedidosPorEstado(),
            'masVendidos' => $indicadores->productosMasVendidos(),
            'mejoresClientes' => $comercial->mejoresClientes(),
            'porTipoDeCliente' => $comercial->ventasPorTipoDeCliente(),
            'clientesAtendidos' => $comercial->clientesAtendidos(),
            'promociones' => $comercial->promocionesVigentes(),
            'sinVentas' => $comercial->productosSinVentas(),
            'descuento' => $comercial->descuentoEntregado(),
            'porMetodoDePago' => $comercial->ventasPorMetodoDePago(),
            'porCobrar' => $comercial->porCobrar(),
            'puedeGestionarPromociones' => $request->user()->rol->puedeGestionarPromociones(),
        ]);
    }
}
