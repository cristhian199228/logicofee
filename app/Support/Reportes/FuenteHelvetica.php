<?php

namespace App\Support\Reportes;

/**
 * Métricas de las dos variantes de Helvetica que usa el PDF. Las fuentes base
 * del formato no traen sus anchos dentro del archivo, así que hay que medir
 * aquí para poder centrar, alinear a la derecha y recortar sin desbordes.
 */
class FuenteHelvetica
{
    /** Anchos en milésimas de punto de los caracteres imprimibles ASCII. */
    private const ANCHOS_NORMAL = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
        111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
        118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
        125 => 334, 126 => 584,
    ];

    private const ANCHOS_NEGRITA = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
        64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611,
        111 => 611, 112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611,
        118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280,
        125 => 389, 126 => 584,
    ];

    /**
     * Byte de Windows-1252 que no es ASCII, con el carácter cuyo ancho comparte.
     * Las vocales acentuadas miden lo mismo que la vocal que las lleva.
     */
    private const EQUIVALENTES = [
        0x80 => '@', 0x85 => 'W', 0x91 => "'", 0x92 => "'", 0x93 => '"', 0x94 => '"',
        0x95 => '-', 0x96 => 'n', 0x97 => 'm', 0xA0 => ' ', 0xA1 => '!', 0xAB => 'c',
        0xB0 => 'r', 0xB7 => '.', 0xBB => 'c', 0xBF => '?',
        0xC0 => 'A', 0xC1 => 'A', 0xC2 => 'A', 0xC3 => 'A', 0xC4 => 'A', 0xC5 => 'A',
        0xC7 => 'C', 0xC8 => 'E', 0xC9 => 'E', 0xCA => 'E', 0xCB => 'E',
        0xCC => 'I', 0xCD => 'I', 0xCE => 'I', 0xCF => 'I', 0xD1 => 'N',
        0xD2 => 'O', 0xD3 => 'O', 0xD4 => 'O', 0xD5 => 'O', 0xD6 => 'O',
        0xD9 => 'U', 0xDA => 'U', 0xDB => 'U', 0xDC => 'U', 0xDD => 'Y',
        0xE0 => 'a', 0xE1 => 'a', 0xE2 => 'a', 0xE3 => 'a', 0xE4 => 'a', 0xE5 => 'a',
        0xE7 => 'c', 0xE8 => 'e', 0xE9 => 'e', 0xEA => 'e', 0xEB => 'e',
        0xEC => 'i', 0xED => 'i', 0xEE => 'i', 0xEF => 'i', 0xF1 => 'n',
        0xF2 => 'o', 0xF3 => 'o', 0xF4 => 'o', 0xF5 => 'o', 0xF6 => 'o',
        0xF9 => 'u', 0xFA => 'u', 0xFB => 'u', 0xFC => 'u', 0xFD => 'y', 0xFF => 'y',
    ];

    /** Signos que la codificación del PDF no tiene y se reemplazan antes de convertir. */
    private const REEMPLAZOS = [
        '→' => '>', '←' => '<', '✓' => 'OK', '✕' => 'x', '≤' => '<=', '≥' => '>=',
        '⚡' => '', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", "\u{00A0}" => ' ',
    ];

    /**
     * Pasa el texto a Windows-1252, la codificación con la que se declaran las
     * fuentes del documento. Lo que no exista en ella se cambia por un guion.
     */
    public static function codificar(string $texto): string
    {
        $texto = strtr($texto, self::REEMPLAZOS);
        $texto = preg_replace('/[\r\n\t]+/u', ' ', $texto) ?? $texto;

        return (string) mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    /** Ancho del texto ya codificado, en puntos del documento. */
    public static function ancho(string $codificado, float $tamano, bool $negrita = false): float
    {
        $anchos = $negrita ? self::ANCHOS_NEGRITA : self::ANCHOS_NORMAL;
        $milesimas = 0;

        foreach (str_split($codificado) as $caracter) {
            $byte = ord($caracter);

            if (isset(self::EQUIVALENTES[$byte])) {
                $byte = ord(self::EQUIVALENTES[$byte]);
            }

            $milesimas += $anchos[$byte] ?? 556;
        }

        return $milesimas * $tamano / 1000;
    }

    /** Corta el texto con puntos suspensivos cuando no entra en la columna. */
    public static function recortar(string $codificado, float $anchoMaximo, float $tamano, bool $negrita = false): string
    {
        if (self::ancho($codificado, $tamano, $negrita) <= $anchoMaximo) {
            return $codificado;
        }

        $puntos = "\x85";
        $limite = max(0.0, $anchoMaximo - self::ancho($puntos, $tamano, $negrita));
        $recortado = '';

        foreach (str_split($codificado) as $caracter) {
            if (self::ancho($recortado.$caracter, $tamano, $negrita) > $limite) {
                break;
            }

            $recortado .= $caracter;
        }

        return rtrim($recortado).$puntos;
    }

    /**
     * Parte el texto en las líneas que caben en el ancho dado, cortando por
     * palabras siempre que se pueda.
     *
     * @return list<string>
     */
    public static function enLineas(string $codificado, float $anchoMaximo, float $tamano, bool $negrita = false): array
    {
        $lineas = [];
        $actual = '';

        foreach (explode(' ', $codificado) as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;

            if (self::ancho($prueba, $tamano, $negrita) <= $anchoMaximo) {
                $actual = $prueba;

                continue;
            }

            if ($actual !== '') {
                $lineas[] = $actual;
            }

            $actual = self::recortar($palabra, $anchoMaximo, $tamano, $negrita);
        }

        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }
}
