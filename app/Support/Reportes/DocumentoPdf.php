<?php

namespace App\Support\Reportes;

/**
 * Escritor mínimo de PDF 1.4 con las fuentes base Helvetica. Dibuja las
 * primitivas que necesitan los reportes —rectángulos, líneas y texto— y arma
 * el archivo con su tabla de referencias cruzadas.
 *
 * Las coordenadas se reciben con el origen arriba a la izquierda, que es como
 * se piensa una hoja, y se traducen al sistema del formato al escribirlas.
 */
class DocumentoPdf
{
    public const ALINEADO_IZQUIERDA = 'izquierda';

    public const ALINEADO_DERECHA = 'derecha';

    public const ALINEADO_CENTRO = 'centro';

    /** @var list<string> */
    private array $paginas = [];

    private string $flujo = '';

    private bool $paginaAbierta = false;

    public function __construct(
        public readonly float $ancho = 842.0,
        public readonly float $alto = 595.0,
        private string $titulo = 'Reporte',
    ) {}

    public function nuevaPagina(): void
    {
        $this->cerrarPagina();

        $this->flujo = '';
        $this->paginaAbierta = true;
    }

    public function totalDePaginas(): int
    {
        return count($this->paginas) + ($this->paginaAbierta ? 1 : 0);
    }

    /**
     * @param  array{int, int, int}  $color
     */
    public function rectangulo(float $x, float $y, float $ancho, float $alto, array $color): void
    {
        $this->flujo .= sprintf(
            "%s rg %.2F %.2F %.2F %.2F re f\n",
            $this->color($color),
            $x,
            $this->alto - $y - $alto,
            $ancho,
            $alto,
        );
    }

    /**
     * @param  array{int, int, int}  $color
     */
    public function linea(float $x1, float $y1, float $x2, float $y2, array $color, float $grosor = 0.5): void
    {
        $this->flujo .= sprintf(
            "%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $this->color($color),
            $grosor,
            $x1,
            $this->alto - $y1,
            $x2,
            $this->alto - $y2,
        );
    }

    /**
     * Escribe una línea de texto. Con `$ancho` el texto se recorta para no
     * invadir la columna vecina y se alinea dentro de ese espacio.
     *
     * @param  array{int, int, int}  $color
     */
    public function texto(
        string $texto,
        float $x,
        float $y,
        float $tamano = 10.0,
        bool $negrita = false,
        array $color = [30, 61, 36],
        ?float $ancho = null,
        string $alineacion = self::ALINEADO_IZQUIERDA,
    ): void {
        $codificado = FuenteHelvetica::codificar($texto);

        if ($ancho !== null) {
            $codificado = FuenteHelvetica::recortar($codificado, $ancho, $tamano, $negrita);
        }

        $desplazamiento = match ($alineacion) {
            self::ALINEADO_DERECHA => ($ancho ?? 0.0) - FuenteHelvetica::ancho($codificado, $tamano, $negrita),
            self::ALINEADO_CENTRO => (($ancho ?? 0.0) - FuenteHelvetica::ancho($codificado, $tamano, $negrita)) / 2,
            default => 0.0,
        };

        $this->flujo .= sprintf(
            "BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->color($color),
            $negrita ? 'F2' : 'F1',
            $tamano,
            $x + $desplazamiento,
            $this->alto - $y,
            $this->escapar($codificado),
        );
    }

    /** Ancho que ocuparía el texto, para repartir columnas antes de dibujar. */
    public function anchoDe(string $texto, float $tamano = 10.0, bool $negrita = false): float
    {
        return FuenteHelvetica::ancho(FuenteHelvetica::codificar($texto), $tamano, $negrita);
    }

    /** Documento completo, listo para descargarse. */
    public function contenido(): string
    {
        $this->cerrarPagina();

        if ($this->paginas === []) {
            $this->paginas[] = '';
        }

        $objetos = $this->objetos();
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];

        foreach ($objetos as $numero => $cuerpo) {
            $posiciones[$numero] = strlen($pdf);
            $pdf .= $numero." 0 obj\n".$cuerpo."\nendobj\n";
        }

        $inicioTabla = strlen($pdf);
        $total = count($objetos) + 1;

        $pdf .= "xref\n0 ".$total."\n0000000000 65535 f \n";

        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }

        return $pdf."trailer\n<< /Size ".$total." /Root 1 0 R /Info 5 0 R >>\nstartxref\n".$inicioTabla."\n%%EOF";
    }

    /**
     * Objetos del documento numerados: los cinco primeros son fijos y luego va
     * un par página/contenido por cada hoja.
     *
     * @return array<int, string>
     */
    private function objetos(): array
    {
        $identificadores = [];

        foreach (array_keys($this->paginas) as $indice) {
            $identificadores[] = 6 + $indice * 2;
        }

        $referencias = implode(' ', array_map(fn (int $id) => $id.' 0 R', $identificadores));

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Count '.count($this->paginas).' /Kids ['.$referencias.'] >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Title ('.$this->escapar(FuenteHelvetica::codificar($this->titulo)).')'
                .' /Author (LogiCoffee) /Creator (LogiCoffee) /Producer (LogiCoffee)'
                .' /CreationDate (D:'.date('YmdHis').') >>',
        ];

        foreach ($this->paginas as $indice => $flujo) {
            $pagina = 6 + $indice * 2;
            $comprimido = (string) gzcompress($flujo, 9);

            $objetos[$pagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->ancho,
                $this->alto,
                $pagina + 1,
            );

            $objetos[$pagina + 1] = '<< /Length '.strlen($comprimido)." /Filter /FlateDecode >>\nstream\n".$comprimido."\nendstream";
        }

        return $objetos;
    }

    private function cerrarPagina(): void
    {
        if ($this->paginaAbierta) {
            $this->paginas[] = $this->flujo;
            $this->paginaAbierta = false;
            $this->flujo = '';
        }
    }

    /**
     * @param  array{int, int, int}  $color
     */
    private function color(array $color): string
    {
        return sprintf('%.3F %.3F %.3F', $color[0] / 255, $color[1] / 255, $color[2] / 255);
    }

    /** Los paréntesis y la barra invertida delimitan las cadenas del formato. */
    private function escapar(string $codificado): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $codificado);
    }
}
