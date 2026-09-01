<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarritoTest extends TestCase
{
    use RefreshDatabase;

    public function test_agrega_un_producto_al_pedido(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        $this->actingAs($this->cliente())
            ->post(route('carrito.store'), ['producto' => $producto->slug])
            ->assertRedirect();

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_no_agrega_mas_unidades_de_las_que_hay_en_almacen(): void
    {
        $producto = Producto::factory()->conStock(1)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)
            ->post(route('carrito.store'), ['producto' => $producto->slug])
            ->assertSessionHas('aviso');

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_aumenta_y_reduce_la_cantidad_de_una_linea(): void
    {
        $producto = Producto::factory()->conStock(10)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->patch(route('carrito.update', $producto), ['delta' => 1]);

        $this->assertSame([$producto->id => 2], session('carrito'));

        $this->actingAs($cliente)->patch(route('carrito.update', $producto), ['delta' => -1]);

        $this->assertSame([$producto->id => 1], session('carrito'));
    }

    public function test_llegar_a_cero_unidades_elimina_la_linea(): void
    {
        $producto = Producto::factory()->conStock(10)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->patch(route('carrito.update', $producto), ['delta' => -1]);

        $this->assertSame([], session('carrito'));
    }

    public function test_quita_un_producto_del_pedido(): void
    {
        $producto = Producto::factory()->conStock(10)->create();
        $cliente = $this->cliente();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)
            ->delete(route('carrito.destroy', $producto))
            ->assertRedirect();

        $this->assertSame([], session('carrito'));
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
