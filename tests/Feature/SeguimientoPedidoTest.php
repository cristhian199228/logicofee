<?php

namespace Tests\Feature;

use App\Enums\EstadoPago;
use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeguimientoPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tablero_muestra_las_tres_etapas(): void
    {
        Pedido::factory()->create(['codigo' => 'PED-101']);
        Pedido::factory()->enEstado(EstadoPedido::Preparacion)->create(['codigo' => 'PED-102']);
        Pedido::factory()->enEstado(EstadoPedido::Entregado)->create(['codigo' => 'PED-103']);

        $this->actingAs($this->proveedor())
            ->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('Pendiente')
            ->assertSee('Preparación')
            ->assertSee('Entregado')
            ->assertSee('PED-101')
            ->assertSee('PED-102')
            ->assertSee('PED-103');
    }

    public function test_avanza_de_pendiente_a_preparacion(): void
    {
        $pedido = Pedido::factory()->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertDispatched('pedido-avanzado');

        $this->assertSame(EstadoPedido::Preparacion, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->entregado_at);
    }

    public function test_avanza_de_preparacion_a_entregado_y_sella_la_hora(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Preparacion)->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar');

        $pedido->refresh();

        $this->assertSame(EstadoPedido::Entregado, $pedido->estado);
        $this->assertNotNull($pedido->entregado_at);
    }

    public function test_un_pedido_entregado_ya_no_avanza(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertDispatched('aviso');

        $this->assertSame(EstadoPedido::Entregado, $pedido->fresh()->estado);
    }

    public function test_el_cliente_sigue_sus_pedidos_en_modo_consulta(): void
    {
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        Pedido::factory()->create(['codigo' => 'PED-101', 'user_id' => $cliente->id]);
        Pedido::factory()->create(['codigo' => 'PED-102', 'user_id' => null]);

        $this->actingAs($cliente)
            ->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('PED-101')
            ->assertDontSee('PED-102')
            ->assertDontSee('Marcar preparación');
    }

    public function test_el_proveedor_ve_el_boton_para_avanzar(): void
    {
        Pedido::factory()->create(['codigo' => 'PED-101']);

        $this->actingAs($this->proveedor())
            ->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('Marcar preparación');
    }

    public function test_la_entrega_anota_quien_recibio_y_cobra_el_efectivo(): void
    {
        $pedido = Pedido::factory()
            ->porCobrar(MetodoPago::Efectivo)
            ->enEstado(EstadoPedido::Preparacion)
            ->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->set('recibido_por', 'Ana Quispe')
            ->set('cobrado', true)
            ->call('avanzar')
            ->assertHasNoErrors();

        $pedido->refresh();

        $this->assertSame(EstadoPedido::Entregado, $pedido->estado);
        $this->assertSame('Ana Quispe', $pedido->entrega_recibido_por);
        $this->assertSame(EstadoPago::Pagado, $pedido->estado_pago);
        $this->assertSame('COB-'.$pedido->codigo, $pedido->referencia_pago);
        $this->assertNotNull($pedido->entregado_at);
    }

    public function test_la_entrega_sin_cobro_deja_el_pago_pendiente(): void
    {
        $pedido = Pedido::factory()
            ->porCobrar(MetodoPago::Efectivo)
            ->enEstado(EstadoPedido::Preparacion)
            ->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->set('recibido_por', 'Ana Quispe')
            ->call('avanzar');

        $this->assertSame(EstadoPago::Pendiente, $pedido->fresh()->estado_pago);
    }

    public function test_pasar_a_preparacion_no_anota_datos_de_entrega(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Pendiente)->create();

        Livewire::actingAs($this->proveedor())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->set('recibido_por', 'Ana Quispe')
            ->set('cobrado', true)
            ->call('avanzar');

        $pedido->refresh();

        $this->assertSame(EstadoPedido::Preparacion, $pedido->estado);
        $this->assertNull($pedido->entrega_recibido_por);
        $this->assertSame(EstadoPago::Pendiente, $pedido->estado_pago);
    }

    public function test_el_tablero_muestra_el_medio_de_pago_y_la_forma_de_entrega(): void
    {
        Pedido::factory()->pagadoCon(MetodoPago::Yape)->recojoEnTienda()->create(['codigo' => 'PED-777']);

        $this->actingAs($this->proveedor())
            ->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('PED-777')
            ->assertSee('Recojo en tienda')
            ->assertSee('Yape · Pagado');
    }

    public function test_el_cliente_no_avanza_pedidos(): void
    {
        $pedido = Pedido::factory()->create();

        Livewire::actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertForbidden();

        $this->assertSame(EstadoPedido::Pendiente, $pedido->fresh()->estado);
    }

    private function proveedor(): User
    {
        return User::factory()->conRol(Rol::Proveedor)->create();
    }
}
