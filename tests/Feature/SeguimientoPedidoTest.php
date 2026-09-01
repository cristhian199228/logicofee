<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($this->proveedor())
            ->post(route('pedidos.avance.store', $pedido))
            ->assertRedirect();

        $this->assertSame(EstadoPedido::Preparacion, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->entregado_at);
    }

    public function test_avanza_de_preparacion_a_entregado_y_sella_la_hora(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Preparacion)->create();

        $this->actingAs($this->proveedor())->post(route('pedidos.avance.store', $pedido));

        $pedido->refresh();

        $this->assertSame(EstadoPedido::Entregado, $pedido->estado);
        $this->assertNotNull($pedido->entregado_at);
    }

    public function test_un_pedido_entregado_ya_no_avanza(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();

        $this->actingAs($this->proveedor())
            ->post(route('pedidos.avance.store', $pedido))
            ->assertSessionHas('aviso');

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

    public function test_el_cliente_no_avanza_pedidos(): void
    {
        $pedido = Pedido::factory()->create();

        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->post(route('pedidos.avance.store', $pedido))
            ->assertForbidden();

        $this->assertSame(EstadoPedido::Pendiente, $pedido->fresh()->estado);
    }

    private function proveedor(): User
    {
        return User::factory()->conRol(Rol::Proveedor)->create();
    }
}
