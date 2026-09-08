<?php

namespace App\Enums;

/**
 * Formatos en los que se descarga un reporte del sistema.
 */
enum FormatoReporte: string
{
    case Pdf = 'pdf';
    case Excel = 'excel';

    public function titulo(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Excel => 'Excel',
        };
    }

    /** Extensión con la que el navegador guarda el archivo. */
    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Excel => 'xlsx',
        };
    }

    public function tipoMime(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
