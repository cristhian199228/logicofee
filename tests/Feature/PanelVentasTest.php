<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelVentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_ordena_los_clientes_por_lo_que_compraron(): void
    {
        Pedido::factory()->count(2)->create(['cliente_nombre' => 'Cafetería Andina', 'total' => 200.00]);
        Pedido::factory()->create(['cliente_nombre' => 'Bodega Los Andes', 'total' => 150.00]);

        $comercial = Livewire::actingAs($this->marketing())
            ->test('ventas')
            ->assertSee('Cafetería Andina')
            ->instance()
            ->comercial;

        $this->assertSame(2, $comercial->clientesAtendidos());

        $mejor = $comercial->mejoresClientes()->first();

        $this->assertSame('Cafetería Andina', $mejor['nombre']);
        $this->assertSame(2, $mejor['pedidos']);
        $this->assertSame(400.00, $mejor['total']);
    }

    public function test_el_panel_reparte_las_ventas_por_tipo_de_cliente(): void
    {
        Pedido::factory()->create(['cliente_tipo' => 'Cafetería', 'total' => 300.00]);
        Pedido::factory()->create(['cliente_tipo' => 'Bodega', 'total' => 100.00]);

        $tipos = Livewire::actingAs($this->marketing())
            ->test('ventas')
            ->instance()
            ->comercial
            ->ventasPorTipoDeCliente();

        $this->assertSame('Cafetería', $tipos->first()['tipo']);
        $this->assertSame(300.00, $tipos->first()['total']);
    }

    public function test_el_panel_mide_el_descuento_entregado_en_promociones(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Geisha Blend', 'precio' => 20.00]);

        // La línea guarda el precio cobrado: 5 uds con $4 menos que el de lista.
        PedidoLinea::factory()->deProducto($producto, 5)->create(['precio' => 16.00]);

        $descuento = Livewire::actingAs($this->marketing())
            ->test('ventas')
            ->instance()
            ->comercial
            ->descuentoEntregado();

        $this->assertSame(20.00, $descuento['descuento']);
        $this->assertSame(5, $descuento['unidades']);
    }

    public function test_el_panel_lista_las_promociones_vigentes_y_el_catalogo_sin_ventas(): void
    {
        $enPromocion = Producto::factory()->enPromocion(25)->create(['nombre' => 'Bourbon Salvador']);
        Producto::factory()->create(['nombre' => 'Mocha Espresso']);

        PedidoLinea::factory()->deProducto($enPromocion, 8)->create();

        $comercial = Livewire::actingAs($this->marketing())
            ->test('ventas')
            ->assertSee('Bourbon Salvador')
            ->assertSee('Mocha Espresso')
            ->instance()
            ->comercial;

        $promociones = $comercial->promocionesVigentes();

        $this->assertCount(1, $promociones);
        $this->assertSame(8, (int) $promociones->first()->unidades_vendidas);
        $this->assertSame(['Mocha Espresso'], $comercial->productosSinVentas()->pluck('nombre')->all());
    }

    public function test_marketing_ve_el_acceso_a_promociones_y_direccion_no(): void
    {
        $this->actingAs($this->marketing())
            ->get(route('ventas.index'))
            ->assertSee('Gestionar promociones');

        $this->actingAs(User::factory()->conRol(Rol::DireccionGeneral)->create())
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertDontSee('Gestionar promociones');
    }

    public function test_solo_las_areas_comerciales_abren_el_panel(): void
    {
        foreach ([Rol::Administrador, Rol::DireccionGeneral, Rol::MarketingVentas] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('ventas.index'))
                ->assertOk();
        }

        foreach ([Rol::LogisticaAlmacen, Rol::ProduccionOperaciones, Rol::Proveedor, Rol::Cliente] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('ventas.index'))
                ->assertForbidden();
        }
    }

    private function marketing(): User
    {
        return User::factory()->conRol(Rol::MarketingVentas)->create();
    }
}
