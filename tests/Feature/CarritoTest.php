<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CarritoTest extends TestCase
{
    use RefreshDatabase;

    public function test_agrega_un_producto_al_pedido_desde_el_catalogo(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        Livewire::actingAs($this->cliente())
            ->test('producto-tarjeta', ['producto' => $producto])
            ->call('agregar')
            ->assertDispatched('carrito-actualizado');

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_no_agrega_mas_unidades_de_las_que_hay_en_almacen(): void
    {
        $producto = Producto::factory()->conStock(1)->create();

        Livewire::actingAs($this->cliente())
            ->test('producto-tarjeta', ['producto' => $producto])
            ->call('agregar')
            ->call('agregar')
            ->assertDispatched('aviso');

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_aumenta_y_reduce_la_cantidad_de_una_linea(): void
    {
        $producto = Producto::factory()->conStock(10)->create();
        $cliente = $this->cliente();

        app(Carrito::class)->agregar($producto);

        $pedido = Livewire::actingAs($cliente)
            ->test('pedido-registrar')
            ->call('sumar', $producto->id);

        $this->assertSame([$producto->id => 2], session('carrito'));

        $pedido->call('restar', $producto->id);

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_llegar_a_cero_unidades_elimina_la_linea(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        app(Carrito::class)->agregar($producto);

        Livewire::actingAs($this->cliente())
            ->test('pedido-registrar')
            ->call('restar', $producto->id);

        $this->assertSame([], session('carrito'));
    }

    public function test_quita_un_producto_del_pedido(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        app(Carrito::class)->agregar($producto, 3);

        Livewire::actingAs($this->cliente())
            ->test('pedido-registrar')
            ->call('quitar', $producto->id)
            ->assertDispatched('carrito-actualizado');

        $this->assertSame([], session('carrito'));
    }

    public function test_el_contador_de_la_cabecera_refleja_las_unidades(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        $contador = Livewire::actingAs($this->cliente())
            ->test('carrito-contador')
            ->assertSet('unidades', 0);

        app(Carrito::class)->agregar($producto, 2);

        $contador->dispatch('carrito-actualizado')->assertSet('unidades', 2);
    }

    public function test_el_total_suma_el_envio_solo_cuando_hay_productos(): void
    {
        $producto = Producto::factory()->conStock(10)->create(['precio' => 10.00]);
        $carrito = app(Carrito::class);

        $this->assertSame(0.0, $carrito->envio());
        $this->assertSame(0.0, $carrito->total());

        $carrito->agregar($producto, 2);

        $this->assertSame(20.0, $carrito->subtotal());
        $this->assertSame(20.0 + config('logicoffee.envio'), $carrito->total());
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }
}
