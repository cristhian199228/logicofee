<?php

namespace App\Support\Reportes;

use App\Enums\TipoCelda;
use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * Arma un libro de Excel (.xlsx) a partir de un reporte, sin librerías: el
 * formato es un zip con las hojas en XML. Cada tabla se escribe en su propia
 * hoja, con encabezado fijo, filtros y formatos de número.
 */
class EscritorExcel
{
    private const VERDE = '2C5530';

    private const VERDE_OSCURO = '1E3D24';

    private const VERDE_CLARO = 'CFE3B8';

    private const CREMA = 'F4F8EE';

    private const GRIS = '748470';

    /** Estilos de `styles.xml` en el orden en que se declaran en cellXfs. */
    private const ESTILO_TITULO = 1;

    private const ESTILO_SUBTITULO = 2;

    private const ESTILO_ETIQUETA = 3;

    private const ESTILO_VALOR = 4;

    private const ESTILO_ENCABEZADO = 5;

    private const ESTILO_TEXTO = 6;

    private const ESTILO_TEXTO_FUERTE = 7;

    private const ESTILO_SECCION = 12;

    private const ESTILO_ENCABEZADO_DERECHA = 13;

    public function generar(Reporte $reporte): string
    {
        $hojas = $this->hojas($reporte);
        $archivo = tempnam(sys_get_temp_dir(), 'logicoffee-');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo preparar el archivo de Excel.');
        }

        $zip = new ZipArchive;

        if ($zip->open($archivo, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el libro de Excel para escribirlo.');
        }

        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido(count($hojas)));
        $zip->addFromString('_rels/.rels', $this->relacionesRaiz());
        $zip->addFromString('docProps/core.xml', $this->propiedades($reporte));
        $zip->addFromString('xl/workbook.xml', $this->libro($hojas));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesDelLibro(count($hojas)));
        $zip->addFromString('xl/styles.xml', $this->estilos());

        foreach (array_values($hojas) as $indice => $hoja) {
            $zip->addFromString('xl/worksheets/sheet'.($indice + 1).'.xml', $hoja['xml']);
        }

        $zip->close();

        $contenido = (string) file_get_contents($archivo);
        unlink($archivo);

        return $contenido;
    }

    /**
     * Hoja de resumen con los indicadores más una hoja por tabla del reporte.
     *
     * @return list<array{nombre: string, xml: string}>
     */
    private function hojas(Reporte $reporte): array
    {
        $hojas = [[
            'nombre' => 'Resumen',
            'xml' => $this->hojaDeResumen($reporte),
        ]];

        $usados = ['Resumen'];

        foreach ($reporte->tablas as $tabla) {
            $hojas[] = [
                'nombre' => $this->nombreDeHoja($tabla->titulo, $usados),
                'xml' => $this->hojaDeTabla($tabla, $reporte),
            ];
        }

        return $hojas;
    }

    private function hojaDeResumen(Reporte $reporte): string
    {
        $filas = [];
        $numero = 1;

        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, $reporte->titulo, self::ESTILO_TITULO)], 26.0);
        $numero++;
        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, $reporte->subtitulo, self::ESTILO_SUBTITULO)]);
        $numero++;
        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, $reporte->pieDePagina(), self::ESTILO_SUBTITULO)]);
        $numero += 2;

        if ($reporte->indicadores !== []) {
            $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, 'Indicadores', self::ESTILO_SECCION)]);
            $numero++;

            foreach ($reporte->indicadores as $indicador) {
                $filas[] = $this->fila($numero, [
                    $this->celdaTexto('A', $numero, $indicador->etiqueta, self::ESTILO_ETIQUETA),
                    $this->celdaTexto('B', $numero, $indicador->valor, self::ESTILO_VALOR),
                    $this->celdaTexto('C', $numero, $indicador->detalle ?? '', self::ESTILO_SUBTITULO),
                ]);
                $numero++;
            }

            $numero++;
        }

        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, 'Contenido del libro', self::ESTILO_SECCION)]);
        $numero++;

        foreach ($reporte->tablas as $tabla) {
            $filas[] = $this->fila($numero, [
                $this->celdaTexto('A', $numero, $tabla->titulo, self::ESTILO_TEXTO_FUERTE),
                $this->celdaTexto('B', $numero, count($tabla->filas).' filas', self::ESTILO_TEXTO),
                $this->celdaTexto('C', $numero, $tabla->nota ?? '', self::ESTILO_TEXTO),
            ]);
            $numero++;
        }

        $columnas = '<cols><col min="1" max="1" width="42" customWidth="1"/>'
            .'<col min="2" max="2" width="22" customWidth="1"/>'
            .'<col min="3" max="3" width="52" customWidth="1"/></cols>';

        return $this->hoja($columnas, implode('', $filas), null, null);
    }

    private function hojaDeTabla(TablaReporte $tabla, Reporte $reporte): string
    {
        $filas = [];
        $numero = 1;

        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, $tabla->titulo, self::ESTILO_TITULO)], 24.0);
        $numero++;
        $filas[] = $this->fila($numero, [$this->celdaTexto('A', $numero, $tabla->nota ?? $reporte->subtitulo, self::ESTILO_SUBTITULO)]);
        $numero += 2;

        $filaEncabezado = $numero;
        $celdas = [];

        foreach ($tabla->columnas as $indice => $columna) {
            $celdas[] = $this->celdaTexto(
                $this->letra($indice),
                $filaEncabezado,
                $columna->titulo,
                $columna->tipo->alineadoADerecha() ? self::ESTILO_ENCABEZADO_DERECHA : self::ESTILO_ENCABEZADO,
            );
        }

        $filas[] = $this->fila($numero++, $celdas, 22.0);

        foreach ($tabla->filas as $fila) {
            $celdas = [];

            foreach ($tabla->columnas as $indice => $columna) {
                $celdas[] = $this->celda($this->letra($indice), $numero, $fila[$indice] ?? null, $columna->tipo, $indice === 0);
            }

            $filas[] = $this->fila($numero++, $celdas);
        }

        $ultimaColumna = $this->letra(max(0, count($tabla->columnas) - 1));
        $filtro = $tabla->sinDatos()
            ? null
            : 'A'.$filaEncabezado.':'.$ultimaColumna.($numero - 1);

        return $this->hoja(
            $this->anchos($tabla),
            implode('', $filas),
            $filaEncabezado,
            $filtro,
        );
    }

    private function hoja(string $columnas, string $filas, ?int $filaEncabezado, ?string $filtro): string
    {
        $vista = $filaEncabezado === null
            ? '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            : '<sheetViews><sheetView showGridLines="0" workbookViewId="0">'
                .'<pane ySplit="'.$filaEncabezado.'" topLeftCell="A'.($filaEncabezado + 1).'" activePane="bottomLeft" state="frozen"/>'
                .'</sheetView></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$vista
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .$columnas
            .'<sheetData>'.$filas.'</sheetData>'
            .($filtro === null ? '' : '<autoFilter ref="'.$filtro.'"/>')
            .'</worksheet>';
    }

    /**
     * Ancho de cada columna a partir del texto más largo que va a contener.
     */
    private function anchos(TablaReporte $tabla): string
    {
        $columnas = '';

        foreach ($tabla->columnas as $indice => $columna) {
            $ancho = mb_strlen($columna->titulo) + 4;

            foreach (array_slice($tabla->filas, 0, 300) as $fila) {
                $ancho = max($ancho, mb_strlen($columna->tipo->formatear($fila[$indice] ?? null)) + 3);
            }

            $columnas .= '<col min="'.($indice + 1).'" max="'.($indice + 1).'" width="'
                .number_format(min(52.0, max(11.0, (float) $ancho)), 2, '.', '').'" customWidth="1"/>';
        }

        return '<cols>'.$columnas.'</cols>';
    }

    /**
     * @param  list<string>  $celdas
     */
    private function fila(int $numero, array $celdas, ?float $alto = null): string
    {
        $atributos = $alto === null ? '' : ' ht="'.$alto.'" customHeight="1"';

        return '<row r="'.$numero.'"'.$atributos.'>'.implode('', $celdas).'</row>';
    }

    private function celdaTexto(string $columna, int $fila, string $valor, int $estilo): string
    {
        return '<c r="'.$columna.$fila.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->limpiar($valor).'</t></is></c>';
    }

    /** Celda de datos: el número entra en crudo y Excel le da su formato. */
    private function celda(string $columna, int $fila, mixed $valor, TipoCelda $tipo, bool $primera): string
    {
        $referencia = $columna.$fila;

        if ($valor === null || $valor === '') {
            return '<c r="'.$referencia.'" s="'.self::ESTILO_TEXTO.'"/>';
        }

        if ($tipo->esNumerico()) {
            return '<c r="'.$referencia.'" s="'.$this->estiloNumerico($tipo).'"><v>'
                .number_format((float) $valor, 4, '.', '').'</v></c>';
        }

        if ($tipo === TipoCelda::Fecha && $valor instanceof DateTimeInterface) {
            return '<c r="'.$referencia.'" s="11"><v>'.$this->serialDeFecha($valor).'</v></c>';
        }

        $estilo = $primera ? self::ESTILO_TEXTO_FUERTE : self::ESTILO_TEXTO;

        return '<c r="'.$referencia.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->limpiar($tipo->formatear($valor)).'</t></is></c>';
    }

    private function estiloNumerico(TipoCelda $tipo): int
    {
        return match ($tipo) {
            TipoCelda::Moneda => 9,
            TipoCelda::Porcentaje => 10,
            default => 8,
        };
    }

    /** Excel cuenta los días desde el 30/12/1899 en el huso del servidor. */
    private function serialDeFecha(DateTimeInterface $fecha): string
    {
        return number_format(25569 + ($fecha->getTimestamp() + $fecha->getOffset()) / 86400, 5, '.', '');
    }

    private function letra(int $indice): string
    {
        $letra = '';

        for ($resto = $indice; $resto >= 0; $resto = intdiv($resto, 26) - 1) {
            $letra = chr(65 + $resto % 26).$letra;
        }

        return $letra;
    }

    /**
     * Nombre de hoja válido: hasta 31 caracteres, sin los signos que Excel
     * reserva y sin repetirse dentro del libro.
     *
     * @param  list<string>  $usados
     */
    private function nombreDeHoja(string $titulo, array &$usados): string
    {
        $nombre = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $titulo));
        $nombre = mb_substr($nombre === '' ? 'Hoja' : $nombre, 0, 31);
        $base = $nombre;
        $intento = 2;

        while (in_array($nombre, $usados, true)) {
            $sufijo = ' '.$intento++;
            $nombre = mb_substr($base, 0, 31 - mb_strlen($sufijo)).$sufijo;
        }

        $usados[] = $nombre;

        return $nombre;
    }

    private function limpiar(string $texto): string
    {
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? $texto;

        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function tiposDeContenido(int $hojas): string
    {
        $partes = '';

        for ($indice = 1; $indice <= $hojas; $indice++) {
            $partes .= '<Override PartName="/xl/worksheets/sheet'.$indice.'.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .$partes
            .'</Types>';
    }

    private function relacionesRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>';
    }

    private function propiedades(Reporte $reporte): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->limpiar($reporte->titulo).'</dc:title>'
            .'<dc:creator>'.$this->limpiar($reporte->generadoPor).'</dc:creator>'
            .'<cp:lastModifiedBy>LogiCoffee</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$reporte->generadoEn->toIso8601ZuluString().'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    /**
     * @param  list<array{nombre: string, xml: string}>  $hojas
     */
    private function libro(array $hojas): string
    {
        $definiciones = '';

        foreach ($hojas as $indice => $hoja) {
            $definiciones .= '<sheet name="'.$this->limpiar($hoja['nombre']).'" sheetId="'.($indice + 1)
                .'" r:id="rId'.($indice + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$definiciones.'</sheets></workbook>';
    }

    private function relacionesDelLibro(int $hojas): string
    {
        $relaciones = '';

        for ($indice = 1; $indice <= $hojas; $indice++) {
            $relaciones .= '<Relationship Id="rId'.$indice
                .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.$indice.'.xml"/>';
        }

        $relaciones .= '<Relationship Id="rId'.($hojas + 1)
            .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relaciones.'</Relationships>';
    }

    /** Paleta y formatos del libro, en el orden que referencian los estilos. */
    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="4">'
            .'<numFmt numFmtId="164" formatCode="#,##0"/>'
            .'<numFmt numFmtId="165" formatCode="&quot;$&quot;#,##0.00"/>'
            .'<numFmt numFmtId="166" formatCode="0.0&quot;%&quot;"/>'
            .'<numFmt numFmtId="167" formatCode="dd/mm/yyyy"/>'
            .'</numFmts>'
            .'<fonts count="7">'
            .'<font><sz val="11"/><color rgb="FF'.self::VERDE_OSCURO.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF'.self::VERDE_OSCURO.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="18"/><color rgb="FF'.self::VERDE.'"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF'.self::GRIS.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF'.self::VERDE.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="9"/><color rgb="FF'.self::GRIS.'"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.self::VERDE.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.self::CREMA.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FF'.self::VERDE_CLARO.'"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="14">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="6" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="167" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
