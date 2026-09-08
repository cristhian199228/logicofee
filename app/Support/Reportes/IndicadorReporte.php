<?php

namespace App\Support\Reportes;

/**
 * Cifra destacada que encabeza el reporte, igual que las tarjetas del panel.
 */
class IndicadorReporte
{
    public function __construct(
        public string $etiqueta,
        public string $valor,
        public ?string $detalle = null,
    ) {}

    public static function moneda(string $etiqueta, float $valor, ?string $detalle = null): self
    {
        return new self($etiqueta, '$'.number_format($valor, 2), $detalle);
    }

    public static function cantidad(string $etiqueta, int|float $valor, ?string $detalle = null): self
    {
        return new self($etiqueta, number_format((float) $valor, 0), $detalle);
    }
}
