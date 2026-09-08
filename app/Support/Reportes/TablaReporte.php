<?php

namespace App\Support\Reportes;

/**
 * Bloque tabular del reporte: un título, sus columnas y las filas ya resueltas.
 * Los valores viajan en crudo para que cada formato los presente a su manera.
 */
class TablaReporte
{
    /**
     * @param  list<ColumnaReporte>  $columnas
     * @param  list<list<mixed>>  $filas
     */
    public function __construct(
        public string $titulo,
        public array $columnas,
        public array $filas,
        public ?string $nota = null,
        public ?string $vacia = null,
    ) {}

    public function sinDatos(): bool
    {
        return $this->filas === [];
    }

    /** Mensaje que reemplaza a la tabla cuando no hay nada que listar. */
    public function mensajeVacio(): string
    {
        return $this->vacia ?? 'No hay información registrada para este bloque.';
    }

    public function pesoTotal(): float
    {
        return array_sum(array_map(fn (ColumnaReporte $columna) => $columna->peso, $this->columnas));
    }
}
