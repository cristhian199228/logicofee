<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_almacen_lista_los_lotes_de_cada_producto(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Bourbon Salvador']);
        Lote::factory()->for($producto)->conCantidad(40)->create(['codigo' => 'L-2601']);

        $this->actingAs($this->proveedor())
            ->get(route('lotes.index'))
            ->assertOk()
            ->assertSee('Bourbon Salvador')
            ->assertSee('L-2601');
    }

    public function test_registrar_un_lote_aumenta_el_stock_del_producto(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        $this->actingAs($this->proveedor())
            ->post(route('lotes.store'), [
                'producto' => $producto->slug,
                'codigo' => 'L-2620',
                'cantidad' => 75,
                'tostado_at' => now()->subWeek()->toDateString(),
                'vence_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('lotes', ['codigo' => 'L-2620', 'cantidad_disponible' => 75]);
        $this->assertSame(85, $producto->fresh()->stock);
    }

    public function test_el_codigo_de_lote_no_se_repite(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto)->create(['codigo' => 'L-2601']);

        $this->actingAs($this->proveedor())
            ->post(route('lotes.store'), [
                'producto' => $producto->slug,
                'codigo' => 'L-2601',
                'cantidad' => 20,
                'tostado_at' => now()->subWeek()->toDateString(),
                'vence_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('lotes', 1);
    }

    public function test_el_vencimiento_debe_ser_posterior_al_tostado(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->proveedor())
            ->post(route('lotes.store'), [
                'producto' => $producto->slug,
                'codigo' => 'L-2621',
                'cantidad' => 20,
                'tostado_at' => now()->toDateString(),
                'vence_at' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('vence_at');
    }

    public function test_el_pedido_consume_primero_el_lote_que_vence_antes(): void
    {
        $producto = Producto::factory()->create();

        $viejo = Lote::factory()->for($producto)->conCantidad(5)->create([
            'codigo' => 'L-VIEJO',
            'vence_at' => now()->addMonth(),
        ]);
        $nuevo = Lote::factory()->for($producto)->conCantidad(50)->create([
            'codigo' => 'L-NUEVO',
            'vence_at' => now()->addYear(),
        ]);

        $producto->sincronizarStock();
        $producto->consumirDeLotes(8);

        $this->assertSame(0, $viejo->fresh()->cantidad_disponible);
        $this->assertSame(47, $nuevo->fresh()->cantidad_disponible);
        $this->assertSame(47, $producto->fresh()->stock);
    }

    public function test_el_cliente_no_accede_al_almacen(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->get(route('lotes.index'))
            ->assertForbidden();
    }

    public function test_el_cliente_no_registra_lotes(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->post(route('lotes.store'), [
                'producto' => $producto->slug,
                'codigo' => 'L-2622',
                'cantidad' => 10,
                'tostado_at' => now()->subWeek()->toDateString(),
                'vence_at' => now()->addYear()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lotes', 0);
    }

    private function proveedor(): User
    {
        return User::factory()->conRol(Rol::Proveedor)->create();
    }
}
