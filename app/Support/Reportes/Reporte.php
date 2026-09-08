<?php

namespace App\Support\Reportes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reporte ya armado: la portada, sus indicadores y sus tablas. No sabe nada de
 * PDF ni de Excel; cada escritor lo recorre y lo dibuja a su manera.
 */
class Reporte
{
    /**
     * @param  list<IndicadorReporte>  $indicadores
     * @param  list<TablaReporte>  $tablas
     */
    public function __construct(
        public string $titulo,
        public string $subtitulo,
        public string $area,
        public array $indicadores,
        public array $tablas,
        public string $generadoPor,
        public ?Carbon $generadoEn = null,
    ) {
        $this->generadoEn ??= Carbon::now();
    }

    /** Nombre del archivo, con la fecha para no pisar descargas anteriores. */
    public function nombreDeArchivo(string $extension): string
    {
        return Str::slug('logicoffee '.$this->titulo).'-'.$this->generadoEn->format('Y-m-d').'.'.$extension;
    }

    public function pieDePagina(): string
    {
        return 'LogiCoffee · '.$this->titulo.' · generado por '.$this->generadoPor
            .' el '.$this->generadoEn->format('d/m/Y H:i');
    }
}
