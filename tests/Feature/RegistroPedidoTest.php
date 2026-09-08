<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Enums\Seccion;
use App\Enums\TipoEntrega;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class RegistroPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_el_pedido_en_estado_pendiente_y_descuenta_el_stock(): void
    {
        $producto = Producto::factory()->conStock(20)->create(['precio' => 14.50]);
        $cliente = $this->cliente();

        app(Carrito::class)->agregar($producto, 2);

        $this->formulario($cliente)
            ->call('registrar')
            ->assertHasNoErrors()
            ->assertRedirect(route('catalogo.index'));

        $this->assertNotNull(session('pedido_confirmado'));

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

        app(Carrito::class)->agregar($producto);

        $this->formulario()->call('registrar');

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

        app(Carrito::class)->agregar(Producto::factory()->conStock(5)->create());

        $this->formulario()->call('registrar');

        $this->assertDatabaseHas('pedidos', ['codigo' => 'PED-138']);
    }

    public function test_no_registra_el_pedido_sin_los_datos_obligatorios(): void
    {
        $producto = Producto::factory()->conStock(5)->create();

        app(Carrito::class)->agregar($producto);

        Livewire::actingAs($this->cliente())
            ->test('pedido-registrar')
            ->set('cliente_nombre', '')
            ->set('cliente_telefono', '')
            ->call('registrar')
            ->assertHasErrors(['cliente_nombre', 'cliente_telefono']);

        $this->assertDatabaseCount('pedidos', 0);
        $this->assertSame(5, $producto->fresh()->stock);
    }

    public function test_no_registra_el_pedido_sin_productos(): void
    {
        $this->formulario()
            ->call('registrar')
            ->assertHasErrors('carrito');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_no_registra_el_pedido_si_el_stock_se_agoto_mientras_tanto(): void
    {
        $producto = Producto::factory()->conStock(5)->create();

        app(Carrito::class)->agregar($producto);

        // Otro pedido vacía los lotes antes de confirmar este.
        $producto->lotes()->update(['cantidad_disponible' => 0]);
        $producto->sincronizarStock();

        $this->formulario()
            ->call('registrar')
            ->assertHasErrors('carrito');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_los_roles_con_la_seccion_de_pedido_registran_pedidos(): void
    {
        $roles = collect(Rol::cases())->filter(fn (Rol $rol) => $rol->puedeVer(Seccion::Pedido));

        foreach ($roles as $rol) {
            $producto = Producto::factory()->conStock(5)->create();
            $usuario = User::factory()->conRol($rol)->create();

            app(Carrito::class)->agregar($producto);

            $this->formulario($usuario)
                ->call('registrar')
                ->assertHasNoErrors()
                ->assertRedirect(route('catalogo.index'));

            $this->assertDatabaseHas('pedidos', ['user_id' => $usuario->id]);
        }

        $this->assertDatabaseCount('pedidos', $roles->count());
    }

    public function test_las_areas_de_almacen_y_produccion_no_registran_pedidos(): void
    {
        $producto = Producto::factory()->conStock(5)->create();

        foreach ([Rol::LogisticaAlmacen, Rol::ProduccionOperaciones, Rol::DireccionGeneral] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('pedidos.create'))->assertForbidden();

            app(Carrito::class)->agregar($producto);

            $this->formulario($usuario)
                ->call('registrar')
                ->assertForbidden();
        }

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_el_carrito_queda_vacio_despues_de_registrar(): void
    {
        app(Carrito::class)->agregar(Producto::factory()->conStock(5)->create());

        $this->formulario()->call('registrar');

        $this->assertTrue(app(Carrito::class)->vacio());
    }

    /**
     * Formulario de pedido con los datos del cliente ya completados.
     */
    private function formulario(?User $usuario = null): Testable
    {
        return Livewire::actingAs($usuario ?? $this->cliente())
            ->test('pedido-registrar')
            ->set('cliente_nombre', 'Cafetería Andina')
            ->set('cliente_telefono', '945664313')
            ->set('cliente_tipo', 'Cafetería')
            ->set('cliente_direccion', 'Av. Ejército 401, Yanahuara')
            ->set('tipo_entrega', TipoEntrega::Delivery->value)
            ->set('metodo_pago', MetodoPago::Efectivo->value);
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }
}
