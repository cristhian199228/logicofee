<?php

namespace Tests\Unit;

use App\Support\QrSimulado;
use PHPUnit\Framework\TestCase;

class QrSimuladoTest extends TestCase
{
    public function test_la_reticula_es_cuadrada(): void
    {
        $matriz = (new QrSimulado('yape://logicoffee/cobro?monto=52.50&moneda=PEN'))->matriz();

        $this->assertCount(QrSimulado::LADO, $matriz);

        foreach ($matriz as $fila) {
            $this->assertCount(QrSimulado::LADO, $fila);
        }
    }

    public function test_las_tres_esquinas_llevan_el_patron_de_posicion(): void
    {
        $matriz = (new QrSimulado('cobro'))->matriz();
        $ultimo = QrSimulado::LADO - 7;

        foreach ([[0, 0], [0, $ultimo], [$ultimo, 0]] as [$filaBase, $columnaBase]) {
            for ($fila = 0; $fila < 7; $fila++) {
                for ($columna = 0; $columna < 7; $columna++) {
                    $esperado = $fila === 0 || $fila === 6 || $columna === 0 || $columna === 6
                        || ($fila >= 2 && $fila <= 4 && $columna >= 2 && $columna <= 4);

                    $this->assertSame($esperado, $matriz[$filaBase + $fila][$columnaBase + $columna]);
                }
            }
        }
    }

    public function test_el_mismo_contenido_dibuja_siempre_el_mismo_codigo(): void
    {
        $this->assertSame(
            (new QrSimulado('monto=52.50'))->matriz(),
            (new QrSimulado('monto=52.50'))->matriz(),
        );

        $this->assertNotSame(
            (new QrSimulado('monto=52.50'))->matriz(),
            (new QrSimulado('monto=60.50'))->matriz(),
        );
    }
}
