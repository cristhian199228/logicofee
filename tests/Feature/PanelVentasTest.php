<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelVentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_ordena_los_clientes_por_lo_que_compraron(): void
    {
        Pedido::factory()->count(2)->create(['cliente_nombre' => 'Cafetería Andina', 'total' => 200.00]);
        Pedido::factory()->create(['cliente_nombre' => 'Bodega Los Andes', 'total' => 150.00]);

        $this->actingAs($this->marketing())
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Cafetería Andina')
            ->assertViewHas('clientesAtendidos', 2)
            ->assertViewHas('mejoresClientes', fn ($clientes) => $clientes->first()['nombre'] === 'Cafetería Andina'
                && $clientes->first()['pedidos'] === 2
                && $clientes->first()['total'] === 400.00);
    }

    public function test_el_panel_reparte_las_ventas_por_tipo_de_cliente(): void
    {
        Pedido::factory()->create(['cliente_tipo' => 'Cafetería', 'total' => 300.00]);
        Pedido::factory()->create(['cliente_tipo' => 'Bodega', 'total' => 100.00]);

        $this->actingAs($this->marketing())
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertViewHas('porTipoDeCliente', fn ($tipos) => $tipos->first()['tipo'] === 'Cafetería'
                && $tipos->first()['total'] === 300.00);
    }

    public function test_el_panel_mide_el_descuento_entregado_en_promociones(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Geisha Blend', 'precio' => 20.00]);

        // La línea guarda el precio cobrado: 5 uds con $4 menos que el de lista.
        PedidoLinea::factory()->deProducto($producto, 5)->create(['precio' => 16.00]);

        $this->actingAs($this->marketing())
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertViewHas('descuento', fn (array $descuento) => $descuento['descuento'] === 20.00
                && $descuento['unidades'] === 5);
    }

    public function test_el_panel_lista_las_promociones_vigentes_y_el_catalogo_sin_ventas(): void
    {
        $enPromocion = Producto::factory()->enPromocion(25)->create(['nombre' => 'Bourbon Salvador']);
        Producto::factory()->create(['nombre' => 'Mocha Espresso']);

        PedidoLinea::factory()->deProducto($enPromocion, 8)->create();

        $this->actingAs($this->marketing())
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Bourbon Salvador')
            ->assertSee('Mocha Espresso')
            ->assertViewHas('promociones', fn ($promociones) => $promociones->count() === 1
                && (int) $promociones->first()->unidades_vendidas === 8)
            ->assertViewHas('sinVentas', fn ($sinVentas) => $sinVentas->pluck('nombre')->all() === ['Mocha Espresso']);
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
