<?php

namespace Tests\Unit;

use App\Enums\EstadoPedido;
use PHPUnit\Framework\TestCase;

class EstadoPedidoTest extends TestCase
{
    public function test_el_flujo_avanza_de_pendiente_a_entregado(): void
    {
        $this->assertSame(EstadoPedido::Preparacion, EstadoPedido::Pendiente->siguiente());
        $this->assertSame(EstadoPedido::Entregado, EstadoPedido::Preparacion->siguiente());
        $this->assertNull(EstadoPedido::Entregado->siguiente());
    }

    public function test_solo_las_etapas_abiertas_ofrecen_una_accion(): void
    {
        $this->assertSame('Marcar preparación', EstadoPedido::Pendiente->accionSiguiente());
        $this->assertSame('Marcar entregado', EstadoPedido::Preparacion->accionSiguiente());
        $this->assertNull(EstadoPedido::Entregado->accionSiguiente());
    }
}
