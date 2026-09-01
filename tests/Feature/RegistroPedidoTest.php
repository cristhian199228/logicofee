<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_el_pedido_en_estado_pendiente_y_descuenta_el_stock(): void
    {
        $producto = Producto::factory()->conStock(20)->create(['precio' => 14.50]);
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->patch(route('carrito.update', $producto), ['delta' => 1]);

        $this->actingAs($cliente)
            ->post(route('pedidos.store'), $this->datosCliente())
            ->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('pedido_confirmado');

        $pedido = Pedido::sole();
        $envio = (float) config('logicoffee.envio');

        $this->assertSame(EstadoPedido::Pendiente, $pedido->estado);
        $this->assertSame('PED-101', $pedido->codigo);
        $this->assertSame($cliente->id, $pedido->user_id);
        $this->assertEquals(29.00, $pedido->subtotal);
        $this->assertEquals(29.00 + $envio, $pedido->total);
        $this->assertSame(18, $producto->fresh()->stock);
        $this->assertEmpty(session('carrito'));
    }

    public function test_la_linea_conserva_los_datos_del_producto(): void
    {
        $producto = Producto::factory()->conStock(20)->create(['precio' => 22.00]);
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->post(route('pedidos.store'), $this->datosCliente());

        $linea = Pedido::sole()->lineas()->sole();

        $this->assertSame($producto->id, $linea->producto_id);
        $this->assertSame($producto->nombre, $linea->nombre);
        $this->assertSame($producto->presentacion, $linea->presentacion);
        $this->assertEquals(22.00, $linea->precio);
        $this->assertSame(1, $linea->cantidad);
    }

    public function test_los_correlativos_continuan_desde_el_ultimo_pedido(): void
    {
        Pedido::factory()->create(['codigo' => 'PED-137']);

        $producto = Producto::factory()->conStock(5)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->post(route('pedidos.store'), $this->datosCliente());

        $this->assertDatabaseHas('pedidos', ['codigo' => 'PED-138']);
    }

    public function test_no_registra_el_pedido_sin_los_datos_obligatorios(): void
    {
        $producto = Producto::factory()->conStock(5)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);

        $this->actingAs($cliente)
            ->post(route('pedidos.store'), ['cliente_tipo' => 'Cafetería'])
            ->assertSessionHasErrors(['cliente_nombre', 'cliente_telefono']);

        $this->assertDatabaseCount('pedidos', 0);
        $this->assertSame(5, $producto->fresh()->stock);
    }

    public function test_no_registra_el_pedido_sin_productos(): void
    {
        $this->actingAs($this->cliente())
            ->post(route('pedidos.store'), $this->datosCliente())
            ->assertSessionHasErrors('carrito');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_no_registra_el_pedido_si_el_stock_se_agoto_mientras_tanto(): void
    {
        $producto = Producto::factory()->conStock(5)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);

        // Otro pedido vacía los lotes antes de confirmar este.
        $producto->lotes()->update(['cantidad_disponible' => 0]);
        $producto->sincronizarStock();

        $this->actingAs($cliente)
            ->post(route('pedidos.store'), $this->datosCliente())
            ->assertSessionHasErrors('carrito');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_los_tres_roles_registran_pedidos(): void
    {
        foreach (Rol::cases() as $rol) {
            $producto = Producto::factory()->conStock(5)->create();
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->post(route('carrito.store'), ['producto' => $producto->slug]);
            $this->actingAs($usuario)
                ->post(route('pedidos.store'), $this->datosCliente())
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('catalogo.index'));

            $this->assertDatabaseHas('pedidos', ['user_id' => $usuario->id]);
        }

        $this->assertDatabaseCount('pedidos', 3);
    }

    public function test_el_carrito_queda_vacio_despues_de_registrar(): void
    {
        $producto = Producto::factory()->conStock(5)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->post(route('pedidos.store'), $this->datosCliente());

        $this->assertTrue(app(Carrito::class)->vacio());
    }

    /**
     * @return array<string, string>
     */
    private function datosCliente(): array
    {
        return [
            'cliente_nombre' => 'Cafetería Andina',
            'cliente_telefono' => '945664313',
            'cliente_tipo' => 'Cafetería',
        ];
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }
}
