<?php

namespace App\Http\Controllers;

use App\Enums\FormatoReporte;
use App\Enums\Seccion;
use App\Support\Reportes\CatalogoDeReportes;
use App\Support\Reportes\EscritorExcel;
use App\Support\Reportes\EscritorPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga el reporte de una sección del menú en PDF o en Excel. Los filtros
 * que traía la pantalla viajan en la consulta para que el archivo diga lo
 * mismo que el usuario tiene delante.
 */
class DescargaReporteController extends Controller
{
    public function __invoke(Request $request, string $seccion, string $formato): Response
    {
        $seccionElegida = Seccion::tryFrom($seccion);
        $formatoElegido = FormatoReporte::tryFrom($formato);

        abort_if($seccionElegida === null || $formatoElegido === null, 404);
        abort_unless(
            $request->user()->puedeVer($seccionElegida),
            403,
            'Tu rol no tiene acceso a los reportes de esta sección.',
        );

        $reporte = (new CatalogoDeReportes($request->user(), $request->query()))->para($seccionElegida);

        $contenido = $formatoElegido === FormatoReporte::Pdf
            ? (new EscritorPdf)->generar($reporte)
            : (new EscritorExcel)->generar($reporte);

        return response($contenido, Response::HTTP_OK, [
            'Content-Type' => $formatoElegido->tipoMime(),
            'Content-Length' => (string) strlen($contenido),
            'Content-Disposition' => 'attachment; filename="'.$reporte->nombreDeArchivo($formatoElegido->extension()).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
