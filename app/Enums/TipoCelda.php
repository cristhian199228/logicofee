<?php

namespace App\Enums;

use DateTimeInterface;

/**
 * Naturaleza de una columna del reporte: decide cómo se imprime en el PDF y
 * con qué formato entra a la hoja de cálculo.
 */
enum TipoCelda: string
{
    case Texto = 'texto';
    case Numero = 'numero';
    case Moneda = 'moneda';
    case Porcentaje = 'porcentaje';
    case Fecha = 'fecha';

    /** Las cantidades se alinean a la derecha para poder compararlas de un vistazo. */
    public function alineadoADerecha(): bool
    {
        return in_array($this, [self::Numero, self::Moneda, self::Porcentaje], true);
    }

    /** Excel recibe el número en crudo y le aplica su propio formato. */
    public function esNumerico(): bool
    {
        return in_array($this, [self::Numero, self::Moneda, self::Porcentaje], true);
    }

    /** Código de formato de celda de la hoja de cálculo. */
    public function formatoExcel(): ?string
    {
        return match ($this) {
            self::Texto => null,
            self::Numero => '#,##0',
            self::Moneda => '"$"#,##0.00',
            self::Porcentaje => '0.0"%"',
            self::Fecha => 'dd/mm/yyyy',
        };
    }

    /** Texto con el que el valor se imprime en el PDF. */
    public function formatear(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($this) {
            self::Texto => (string) $valor,
            self::Numero => number_format((float) $valor, 0),
            self::Moneda => '$'.number_format((float) $valor, 2),
            self::Porcentaje => number_format((float) $valor, 1).'%',
            self::Fecha => $valor instanceof DateTimeInterface
                ? $valor->format('d/m/Y')
                : (string) $valor,
        };
    }
}
