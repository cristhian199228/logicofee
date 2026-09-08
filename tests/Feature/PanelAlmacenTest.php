<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelAlmacenTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_resume_el_stock_vendible_y_las_mermas(): void
    {
        $producto = Producto::factory()->create(['precio' => 10.00, 'stock_minimo' => 0]);
        Lote::factory()->for($producto)->conCantidad(40)->create();
        Lote::factory()->for($producto)->conCantidad(30)->rechazado()->create();
        Lote::factory()->for($producto)->conCantidad(10)->conMerma(5)->create();
        $producto->sincronizarStock();

        Pedido::factory()->enEstado(EstadoPedido::Pendiente)->create();
        Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();

        $almacen = Livewire::actingAs($this->logistica())
            ->test('almacen')
            ->assertSee('Panel de almacén')
            ->instance()
            ->almacen;

        $this->assertSame(45, $almacen->unidadesDisponibles());
        $this->assertSame(450.00, $almacen->valorInventario());
        $this->assertSame(5, $almacen->unidadesMermadas());
        $this->assertSame(1, $almacen->pedidosPorDespachar());
    }

    public function test_el_panel_sugiere_reponer_los_productos_en_el_minimo(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso', 'stock_minimo' => 20]);
        Producto::factory()->conStock(80)->create(['nombre' => 'Bourbon Salvador', 'stock_minimo' => 10]);

        $reposicion = Livewire::actingAs($this->logistica())
            ->test('almacen')
            ->assertSee('Reponer 40 uds')
            ->instance()
            ->almacen
            ->reposicionSugerida();

        $this->assertCount(1, $reposicion);
        $this->assertSame('Mocha Espresso', $reposicion->first()['producto']->nombre);
        $this->assertSame(40, $reposicion->first()['sugerido']);
    }

    public function test_el_panel_separa_los_lotes_vencidos_de_los_que_estan_por_vencer(): void
    {
        $producto = Producto::factory()->create();
        $vencido = Lote::factory()->for($producto)->conCantidad(12)->vencido()->create(['codigo' => 'L-9001']);
        $porVencer = Lote::factory()->for($producto)->conCantidad(20)->porVencer()->create(['codigo' => 'L-9002']);
        Lote::factory()->for($producto)->conCantidad(30)->create(['codigo' => 'L-9003']);

        $almacen = Livewire::actingAs($this->logistica())
            ->test('almacen')
            ->assertSee('L-9001')
            ->assertSee('L-9002')
            ->instance()
            ->almacen;

        $this->assertSame([$vencido->id], $almacen->lotesVencidos()->pluck('id')->all());
        $this->assertSame([$porVencer->id], $almacen->lotesPorVencer()->pluck('id')->all());
    }

    public function test_la_cobertura_estima_los_dias_de_stock_segun_lo_vendido(): void
    {
        $producto = Producto::factory()->conStock(60)->create(['nombre' => 'Bourbon Salvador']);

        // 30 unidades en la ventana de 30 días: una por día, dos meses de stock.
        PedidoLinea::factory()->deProducto($producto, 30)->create();

        $cobertura = Livewire::actingAs($this->logistica())
            ->test('almacen')
            ->instance()
            ->almacen
            ->coberturaPorProducto();

        $this->assertSame(60.0, $cobertura->firstWhere('producto.nombre', 'Bourbon Salvador')['dias']);
    }

    public function test_direccion_ve_el_panel_pero_sin_los_controles_de_baja(): void
    {
        Lote::factory()->conCantidad(10)->vencido()->create();

        $this->actingAs($this->logistica())
            ->get(route('almacen.index'))
            ->assertSee('Dar de baja');

        $this->actingAs(User::factory()->conRol(Rol::DireccionGeneral)->create())
            ->get(route('almacen.index'))
            ->assertOk()
            ->assertDontSee('Dar de baja');
    }

    public function test_solo_las_areas_de_almacen_abren_el_panel(): void
    {
        foreach ([Rol::Administrador, Rol::DireccionGeneral, Rol::LogisticaAlmacen] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('almacen.index'))
                ->assertOk();
        }

        foreach ([Rol::MarketingVentas, Rol::ProduccionOperaciones, Rol::Proveedor, Rol::Cliente] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('almacen.index'))
                ->assertForbidden();
        }
    }

    private function logistica(): User
    {
        return User::factory()->conRol(Rol::LogisticaAlmacen)->create();
    }
}
