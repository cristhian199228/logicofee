<?php

namespace App\Support;

/**
 * Retícula de un código QR de demostración. No codifica el contenido con el
 * estándar ISO/IEC 18004: reproduce la estructura visible de un QR (patrones
 * de posición, separadores, sincronismo y alineación) y rellena el resto de
 * módulos de forma determinista a partir del texto, de modo que el mismo
 * cobro dibuje siempre el mismo código.
 */
class QrSimulado
{
    /** Módulos por lado, la medida de un QR versión 2. */
    public const LADO = 25;

    /** Módulos del patrón de posición de cada esquina. */
    private const POSICION = 7;

    public function __construct(private readonly string $contenido) {}

    /**
     * Retícula del código, fila por fila: `true` es un módulo oscuro.
     *
     * @return list<list<bool>>
     */
    public function matriz(): array
    {
        $matriz = array_fill(0, self::LADO, array_fill(0, self::LADO, false));
        $reservado = $matriz;

        $this->dibujarPosicion($matriz, $reservado);
        $this->dibujarSincronismo($matriz, $reservado);
        $this->dibujarAlineacion($matriz, $reservado);
        $this->rellenarDatos($matriz, $reservado);

        return $matriz;
    }

    /**
     * Los tres cuadrados de las esquinas, cada uno con su separador claro.
     *
     * @param  list<list<bool>>  $matriz
     * @param  list<list<bool>>  $reservado
     */
    private function dibujarPosicion(array &$matriz, array &$reservado): void
    {
        $ultimo = self::LADO - self::POSICION;

        foreach ([[0, 0], [0, $ultimo], [$ultimo, 0]] as [$filaBase, $columnaBase]) {
            for ($fila = -1; $fila <= self::POSICION; $fila++) {
                for ($columna = -1; $columna <= self::POSICION; $columna++) {
                    $y = $filaBase + $fila;
                    $x = $columnaBase + $columna;

                    if (! $this->dentro($y, $x)) {
                        continue;
                    }

                    $borde = $fila === 0 || $fila === 6 || $columna === 0 || $columna === 6;
                    $centro = $fila >= 2 && $fila <= 4 && $columna >= 2 && $columna <= 4;
                    $separador = $fila === -1 || $fila === self::POSICION || $columna === -1 || $columna === self::POSICION;

                    $matriz[$y][$x] = ! $separador && ($borde || $centro);
                    $reservado[$y][$x] = true;
                }
            }
        }
    }

    /**
     * Las líneas alternadas que unen los patrones de posición.
     *
     * @param  list<list<bool>>  $matriz
     * @param  list<list<bool>>  $reservado
     */
    private function dibujarSincronismo(array &$matriz, array &$reservado): void
    {
        for ($i = self::POSICION + 1; $i < self::LADO - self::POSICION - 1; $i++) {
            $oscuro = $i % 2 === 0;

            $matriz[6][$i] = $oscuro;
            $reservado[6][$i] = true;

            $matriz[$i][6] = $oscuro;
            $reservado[$i][6] = true;
        }
    }

    /**
     * El cuadrado pequeño de la esquina inferior derecha.
     *
     * @param  list<list<bool>>  $matriz
     * @param  list<list<bool>>  $reservado
     */
    private function dibujarAlineacion(array &$matriz, array &$reservado): void
    {
        $base = self::LADO - self::POSICION - 2;

        for ($fila = 0; $fila < 5; $fila++) {
            for ($columna = 0; $columna < 5; $columna++) {
                $borde = $fila === 0 || $fila === 4 || $columna === 0 || $columna === 4;
                $centro = $fila === 2 && $columna === 2;

                $matriz[$base + $fila][$base + $columna] = $borde || $centro;
                $reservado[$base + $fila][$base + $columna] = true;
            }
        }
    }

    /**
     * Módulos libres, tomados del hash del contenido para que el dibujo sea
     * siempre el mismo con el mismo texto.
     *
     * @param  list<list<bool>>  $matriz
     * @param  list<list<bool>>  $reservado
     */
    private function rellenarDatos(array &$matriz, array $reservado): void
    {
        $bits = $this->bits();

        foreach ($matriz as $y => $fila) {
            foreach ($fila as $x => $modulo) {
                if ($reservado[$y][$x]) {
                    continue;
                }

                $matriz[$y][$x] = $bits[$y * self::LADO + $x];
            }
        }
    }

    /**
     * Un bit por módulo, encadenando hashes del contenido hasta cubrir toda
     * la retícula: así el relleno no repite ningún tramo del dibujo.
     *
     * @return list<bool>
     */
    private function bits(): array
    {
        $bits = [];

        for ($ronda = 0; count($bits) < self::LADO * self::LADO; $ronda++) {
            foreach (str_split(hash('sha256', $this->contenido.'|'.$ronda)) as $hexadecimal) {
                foreach (str_split(sprintf('%04b', hexdec($hexadecimal))) as $bit) {
                    $bits[] = $bit === '1';
                }
            }
        }

        return $bits;
    }

    private function dentro(int $fila, int $columna): bool
    {
        return $fila >= 0 && $fila < self::LADO && $columna >= 0 && $columna < self::LADO;
    }
}
