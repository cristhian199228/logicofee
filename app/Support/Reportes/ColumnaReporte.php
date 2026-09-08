<?php

namespace App\Support\Reportes;

use App\Enums\TipoCelda;

/**
 * Columna de una tabla del reporte. El peso reparte el ancho disponible de la
 * hoja entre las columnas, para que ninguna quede apretada en el PDF.
 */
class ColumnaReporte
{
    public function __construct(
        public string $titulo,
        public TipoCelda $tipo = TipoCelda::Texto,
        public float $peso = 1.0,
    ) {}

    public static function texto(string $titulo, float $peso = 2.0): self
    {
        return new self($titulo, TipoCelda::Texto, $peso);
    }

    public static function numero(string $titulo, float $peso = 1.0): self
    {
        return new self($titulo, TipoCelda::Numero, $peso);
    }

    public static function moneda(string $titulo, float $peso = 1.2): self
    {
        return new self($titulo, TipoCelda::Moneda, $peso);
    }

    public static function porcentaje(string $titulo, float $peso = 1.0): self
    {
        return new self($titulo, TipoCelda::Porcentaje, $peso);
    }

    public static function fecha(string $titulo, float $peso = 1.1): self
    {
        return new self($titulo, TipoCelda::Fecha, $peso);
    }
}
