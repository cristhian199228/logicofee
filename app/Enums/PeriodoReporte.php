<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum PeriodoReporte: string
{
    case Dia = 'dia';
    case Semana = 'semana';
    case Mes = 'mes';

    public function titulo(): string
    {
        return match ($this) {
            self::Dia => 'Por día',
            self::Semana => 'Por semana',
            self::Mes => 'Por mes',
        };
    }

    /** Cuántos tramos se dibujan en la gráfica de ventas. */
    public function tramos(): int
    {
        return match ($this) {
            self::Dia => 7,
            self::Semana => 8,
            self::Mes => 6,
        };
    }

    /** Inicio del tramo al que pertenece una fecha. */
    public function inicioDelTramo(Carbon $fecha): Carbon
    {
        return match ($this) {
            self::Dia => $fecha->copy()->startOfDay(),
            self::Semana => $fecha->copy()->startOfWeek(),
            self::Mes => $fecha->copy()->startOfMonth(),
        };
    }

    /** Retrocede la fecha el número de tramos indicado. */
    public function retroceder(Carbon $fecha, int $tramos): Carbon
    {
        return match ($this) {
            self::Dia => $fecha->copy()->subDays($tramos),
            self::Semana => $fecha->copy()->subWeeks($tramos),
            self::Mes => $fecha->copy()->subMonths($tramos),
        };
    }

    /** Etiqueta del tramo en el eje de la gráfica. */
    public function etiquetaDelTramo(Carbon $inicio): string
    {
        return match ($this) {
            self::Dia => $inicio->translatedFormat('d M'),
            self::Semana => 'Sem. '.$inicio->translatedFormat('d M'),
            self::Mes => $inicio->translatedFormat('M Y'),
        };
    }
}
