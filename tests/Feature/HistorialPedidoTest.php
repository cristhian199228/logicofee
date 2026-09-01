<?php

namespace Tests\Feature;

use App\Enums\CategoriaProducto;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_cliente_solo_ve_sus_propios_pedidos(): void
    {
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        Pedido::factory()->create(['codigo' => 'PED-101', 'user_id' => $cliente->id]);
        Pedido::factory()->create(['codigo' => 'PED-102', 'user_id' => null]);

        $this->actingAs($cliente)
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('PED-101')
            ->assertDontSee('PED-102');
    }

    public function test_el_administrador_ve_todos_los_pedidos(): void
    {
        Pedido::factory()->create(['codigo' => 'PED-101']);
        Pedido::factory()->create(['codigo' => 'PED-102']);

        $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('PED-101')
            ->assertSee('PED-102');
    }

    public function test_el_historial_vacio_invita_al_catalogo(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('Todavía no hay pedidos registrados.');
    }

    public function test_el_historial_muestra_las_lineas_del_pedido(): void
    {
        $pedido = Pedido::factory()->create(['codigo' => 'PED-101']);
        $pedido->lineas()->create([
            'producto_id' => null,
            'nombre' => 'Café Bourbon Salvador',
            'presentacion' => '250 g',
            'categoria' => CategoriaProducto::EnGrano,
            'precio' => 14.50,
            'cantidad' => 12,
        ]);

        $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('Café Bourbon Salvador')
            ->assertSee('12x');
    }

    public function test_el_proveedor_ve_todos_los_pedidos(): void
    {
        Pedido::factory()->create(['codigo' => 'PED-101']);
        Pedido::factory()->create(['codigo' => 'PED-102']);

        $this->actingAs(User::factory()->conRol(Rol::Proveedor)->create())
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('PED-101')
            ->assertSee('PED-102');
    }
}
