<?php

namespace App\Support\Reportes;

use App\Enums\TipoCelda;

/**
 * Dibuja un reporte en una hoja A4 apaisada con la identidad de LogiCoffee:
 * banda de portada, tarjetas de indicadores y tablas con encabezado repetido
 * en cada página.
 */
class EscritorPdf
{
    private const MARGEN = 36.0;

    private const ALTO_BANDA = 78.0;

    /** Banda más baja en las páginas de continuación, que no repiten la portada. */
    private const ALTO_BANDA_CONTINUACION = 48.0;

    private const ALTO_PIE = 34.0;

    private const ALTO_FILA = 16.5;

    private const ALTO_ENCABEZADO = 20.0;

    /** Puntos de ancho que recibe cada unidad de peso de una columna. */
    private const ANCHO_POR_PESO = 118.0;

    /** @var array{int, int, int} */
    private const VERDE_OSCURO = [30, 61, 36];

    /** @var array{int, int, int} */
    private const VERDE = [44, 85, 48];

    /** @var array{int, int, int} */
    private const VERDE_CLARO = [207, 227, 184];

    /** @var array{int, int, int} */
    private const CREMA = [244, 248, 238];

    /** @var array{int, int, int} */
    private const BLANCO = [255, 255, 255];

    /** @var array{int, int, int} */
    private const MOSTAZA = [217, 164, 65];

    /** @var array{int, int, int} */
    private const GRIS = [116, 132, 112];

    private DocumentoPdf $documento;

    private float $cursor = 0.0;

    public function generar(Reporte $reporte): string
    {
        // La primera pasada solo sirve para saber cuántas páginas numerar.
        $paginas = $this->dibujar($reporte, null)->totalDePaginas();

        return $this->dibujar($reporte, $paginas)->contenido();
    }

    private function dibujar(Reporte $reporte, ?int $totalDePaginas): DocumentoPdf
    {
        $this->documento = new DocumentoPdf(titulo: $reporte->titulo);
        $this->abrirPagina($reporte, $totalDePaginas, portada: true);
        $this->indicadores($reporte);

        foreach ($reporte->tablas as $tabla) {
            $this->tabla($tabla, $reporte, $totalDePaginas);
        }

        return $this->documento;
    }

    private function abrirPagina(Reporte $reporte, ?int $totalDePaginas, bool $portada = false): void
    {
        $this->documento->nuevaPagina();
        $this->banda($reporte, $portada);
        $this->pie($reporte, $totalDePaginas);

        $this->cursor = ($portada ? self::ALTO_BANDA : self::ALTO_BANDA_CONTINUACION) + 26.0;
    }

    /** Franja verde de la portada con el título, el área y la fecha. */
    private function banda(Reporte $reporte, bool $portada): void
    {
        $alto = $portada ? self::ALTO_BANDA : self::ALTO_BANDA_CONTINUACION;
        $derecha = $this->documento->ancho - self::MARGEN;

        $this->documento->rectangulo(0, 0, $this->documento->ancho, $alto, self::VERDE_OSCURO);
        $this->documento->rectangulo(0, $alto, $this->documento->ancho, 3, self::MOSTAZA);

        $this->documento->texto('LOGICOFFEE', self::MARGEN, $portada ? 26 : 20, 8.5, true, self::MOSTAZA);
        $this->documento->texto($reporte->titulo, self::MARGEN, $portada ? 50 : 38, $portada ? 19 : 14, true, self::BLANCO);

        if ($portada) {
            $this->documento->texto($reporte->subtitulo, self::MARGEN, 67, 8.5, false, self::VERDE_CLARO);
        }

        $etiqueta = $reporte->area;
        $anchoEtiqueta = $this->documento->anchoDe($etiqueta, 8.5, true) + 20;

        $this->documento->rectangulo($derecha - $anchoEtiqueta, $portada ? 20 : 14, $anchoEtiqueta, 19, self::VERDE);
        $this->documento->texto($etiqueta, $derecha - $anchoEtiqueta, $portada ? 33 : 27, 8.5, true, self::BLANCO, $anchoEtiqueta, DocumentoPdf::ALINEADO_CENTRO);

        $this->documento->texto(
            'Emitido el '.$reporte->generadoEn->format('d/m/Y H:i'),
            $derecha - 220,
            $portada ? 55 : 44,
            8,
            false,
            self::VERDE_CLARO,
            220,
            DocumentoPdf::ALINEADO_DERECHA,
        );
    }

    private function pie(Reporte $reporte, ?int $totalDePaginas): void
    {
        $y = $this->documento->alto - self::ALTO_PIE;
        $derecha = $this->documento->ancho - self::MARGEN;

        $this->documento->linea(self::MARGEN, $y, $derecha, $y, self::VERDE_CLARO);
        $this->documento->texto($reporte->pieDePagina(), self::MARGEN, $y + 14, 7.5, false, self::GRIS);

        $numero = 'Página '.$this->documento->totalDePaginas()
            .($totalDePaginas === null ? '' : ' de '.$totalDePaginas);

        $this->documento->texto($numero, $derecha - 120, $y + 14, 7.5, true, self::GRIS, 120, DocumentoPdf::ALINEADO_DERECHA);
    }

    /** Tarjetas con las cifras principales, hasta cinco por fila. */
    private function indicadores(Reporte $reporte): void
    {
        if ($reporte->indicadores === []) {
            return;
        }

        $disponible = $this->documento->ancho - self::MARGEN * 2;
        $porFila = min(5, max(1, count($reporte->indicadores)));
        $separacion = 10.0;
        $ancho = ($disponible - $separacion * ($porFila - 1)) / $porFila;

        foreach (array_chunk($reporte->indicadores, $porFila) as $fila) {
            foreach ($fila as $columna => $indicador) {
                $x = self::MARGEN + $columna * ($ancho + $separacion);

                $this->documento->rectangulo($x, $this->cursor, $ancho, 54, self::VERDE_CLARO);
                $this->documento->rectangulo($x + 0.8, $this->cursor + 0.8, $ancho - 1.6, 52.4, self::BLANCO);
                $this->documento->rectangulo($x + 0.8, $this->cursor + 0.8, 3, 52.4, self::MOSTAZA);

                $interior = $ancho - 18;

                $this->documento->texto(mb_strtoupper($indicador->etiqueta), $x + 12, $this->cursor + 16, 7, true, self::GRIS, $interior);
                $this->documento->texto($indicador->valor, $x + 12, $this->cursor + 36, 15, true, self::VERDE_OSCURO, $interior);

                if ($indicador->detalle !== null) {
                    $this->documento->texto($indicador->detalle, $x + 12, $this->cursor + 48, 7, false, self::GRIS, $interior);
                }
            }

            $this->cursor += 54 + $separacion;
        }

        $this->cursor += 8;
    }

    private function tabla(TablaReporte $tabla, Reporte $reporte, ?int $totalDePaginas): void
    {
        // Empezar la tabla solo si en la hoja entran su título y las primeras filas.
        $this->asegurarEspacio(
            24 + ($tabla->nota === null ? 0 : 14) + self::ALTO_ENCABEZADO + min(2, count($tabla->filas)) * self::ALTO_FILA,
            $reporte,
            $totalDePaginas,
        );

        $this->tituloDeTabla($tabla);

        if ($tabla->sinDatos()) {
            $this->documento->texto($tabla->mensajeVacio(), self::MARGEN, $this->cursor + 12, 8.5, false, self::GRIS);
            $this->cursor += 30;

            return;
        }

        $anchos = $this->anchosDeColumna($tabla);
        $this->encabezado($tabla, $anchos);

        foreach ($tabla->filas as $indice => $fila) {
            if ($this->cursor + self::ALTO_FILA > $this->documento->alto - self::ALTO_PIE - 10) {
                $this->abrirPagina($reporte, $totalDePaginas);
                $this->tituloDeTabla($tabla, continuacion: true);
                $this->encabezado($tabla, $anchos);
            }

            $this->fila($tabla, $anchos, $fila, $indice);
        }

        $this->cursor += 18;
    }

    private function tituloDeTabla(TablaReporte $tabla, bool $continuacion = false): void
    {
        $titulo = $tabla->titulo.($continuacion ? ' (continúa)' : '');

        $this->documento->rectangulo(self::MARGEN, $this->cursor + 1, 3.5, 13, self::MOSTAZA);
        $this->documento->texto($titulo, self::MARGEN + 10, $this->cursor + 12, 12.5, true, self::VERDE_OSCURO);

        $this->cursor += 20;

        if ($tabla->nota !== null && ! $continuacion) {
            $this->documento->texto($tabla->nota, self::MARGEN + 10, $this->cursor + 8, 8, false, self::GRIS);
            $this->cursor += 14;
        }

        $this->cursor += 4;
    }

    /**
     * @param  list<float>  $anchos
     */
    private function encabezado(TablaReporte $tabla, array $anchos): void
    {
        $this->documento->rectangulo(self::MARGEN, $this->cursor, array_sum($anchos), self::ALTO_ENCABEZADO, self::VERDE);

        $x = self::MARGEN;

        foreach ($tabla->columnas as $indice => $columna) {
            $this->documento->texto(
                mb_strtoupper($columna->titulo),
                $x + 6,
                $this->cursor + 13.5,
                7.5,
                true,
                self::BLANCO,
                $anchos[$indice] - 12,
                $columna->tipo->alineadoADerecha() ? DocumentoPdf::ALINEADO_DERECHA : DocumentoPdf::ALINEADO_IZQUIERDA,
            );

            $x += $anchos[$indice];
        }

        $this->cursor += self::ALTO_ENCABEZADO;
    }

    /**
     * @param  list<float>  $anchos
     * @param  list<mixed>  $fila
     */
    private function fila(TablaReporte $tabla, array $anchos, array $fila, int $indice): void
    {
        $total = array_sum($anchos);

        if ($indice % 2 === 1) {
            $this->documento->rectangulo(self::MARGEN, $this->cursor, $total, self::ALTO_FILA, self::CREMA);
        }

        $x = self::MARGEN;

        foreach ($tabla->columnas as $columna => $definicion) {
            $valor = $fila[$columna] ?? null;
            $esTexto = $definicion->tipo === TipoCelda::Texto;

            $this->documento->texto(
                $definicion->tipo->formatear($valor),
                $x + 6,
                $this->cursor + 11.5,
                8.5,
                $columna === 0 && $esTexto,
                self::VERDE_OSCURO,
                $anchos[$columna] - 12,
                $definicion->tipo->alineadoADerecha() ? DocumentoPdf::ALINEADO_DERECHA : DocumentoPdf::ALINEADO_IZQUIERDA,
            );

            $x += $anchos[$columna];
        }

        $this->cursor += self::ALTO_FILA;
        $this->documento->linea(self::MARGEN, $this->cursor, self::MARGEN + $total, $this->cursor, self::VERDE_CLARO, 0.4);
    }

    /**
     * Reparte el ancho útil de la hoja entre las columnas según su peso. Las
     * tablas de pocas columnas no se estiran de borde a borde: se quedan en el
     * ancho que necesitan para que las cifras no se pierdan de vista.
     *
     * @return list<float>
     */
    private function anchosDeColumna(TablaReporte $tabla): array
    {
        $peso = $tabla->pesoTotal() ?: 1.0;
        $disponible = min(
            $this->documento->ancho - self::MARGEN * 2,
            $peso * self::ANCHO_POR_PESO,
        );

        return array_map(
            fn ($columna) => $disponible * $columna->peso / $peso,
            $tabla->columnas,
        );
    }

    /** Salta de página cuando el bloque que sigue ya no entra en la hoja. */
    private function asegurarEspacio(float $alto, Reporte $reporte, ?int $totalDePaginas): void
    {
        if ($this->cursor + $alto > $this->documento->alto - self::ALTO_PIE) {
            $this->abrirPagina($reporte, $totalDePaginas);
        }
    }
}
