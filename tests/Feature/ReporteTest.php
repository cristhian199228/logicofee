<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\PeriodoReporte;
use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_resume_los_pedidos_por_estado(): void
    {
        Pedido::factory()->count(2)->enEstado(EstadoPedido::Pendiente)->create();
        Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();

        $this->actingAs($this->administrador())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Pedidos por estado')
            ->assertSee('Ticket promedio')
            ->assertViewHas('totalPedidos', 3)
            ->assertViewHas('porEstado', fn ($porEstado) => $porEstado->firstWhere('estado', EstadoPedido::Pendiente)['cantidad'] === 2
                && $porEstado->firstWhere('estado', EstadoPedido::Entregado)['cantidad'] === 1);
    }

    public function test_el_panel_lista_los_productos_mas_vendidos(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();
        $estrella = Producto::factory()->create(['nombre' => 'Geisha Blend Premium']);
        $otro = Producto::factory()->create(['nombre' => 'Mocha Espresso']);

        PedidoLinea::factory()->for($pedido)->deProducto($estrella, 12)->create();
        PedidoLinea::factory()->for($pedido)->deProducto($otro, 3)->create();

        $this->actingAs($this->administrador())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Geisha Blend Premium')
            ->assertViewHas('masVendidos', fn ($masVendidos) => $masVendidos->first()['nombre'] === 'Geisha Blend Premium'
                && $masVendidos->first()['unidades'] === 12)
            ->assertViewHas('unidadesVendidas', 15);
    }

    public function test_las_ventas_se_agrupan_por_el_periodo_elegido(): void
    {
        Pedido::factory()->create(['total' => 100.00, 'created_at' => now()]);
        Pedido::factory()->create(['total' => 50.00, 'created_at' => now()->subMonths(2)]);

        $respuesta = $this->actingAs($this->administrador())
            ->get(route('reportes.index', ['periodo' => PeriodoReporte::Mes->value]));

        $respuesta->assertOk()
            ->assertViewHas('periodo', PeriodoReporte::Mes)
            ->assertViewHas('ventas', fn ($ventas) => $ventas->count() === PeriodoReporte::Mes->tramos()
                && $ventas->last()['total'] === 100.00
                && $ventas->sum('total') === 150.00);

        // El periodo por día solo alcanza los últimos siete tramos.
        $this->actingAs($this->administrador())
            ->get(route('reportes.index'))
            ->assertViewHas('periodo', PeriodoReporte::Dia)
            ->assertViewHas('ventas', fn ($ventas) => $ventas->sum('total') === 100.00);
    }

    public function test_el_panel_avisa_del_stock_bajo_y_de_los_lotes_bloqueados(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso', 'stock_minimo' => 20]);

        $abastecido = Producto::factory()->create(['nombre' => 'Bourbon Salvador', 'stock_minimo' => 0]);
        Lote::factory()->for($abastecido)->conCantidad(30)->rechazado()->create();
        Lote::factory()->for($abastecido)->conCantidad(15)->create();
        $abastecido->sincronizarStock();

        $this->actingAs($this->administrador())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Mocha Espresso')
            ->assertViewHas('lotesBloqueados', 1)
            ->assertViewHas('lotesSinEvaluar', 1)
            ->assertViewHas('alertasDeStock', fn ($alertas) => $alertas->pluck('nombre')->all() === ['Mocha Espresso']);
    }

    public function test_solo_administracion_y_direccion_abren_el_panel(): void
    {
        foreach ([Rol::Proveedor, Rol::Cliente, Rol::MarketingVentas, Rol::LogisticaAlmacen, Rol::ProduccionOperaciones] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('reportes.index'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->conRol(Rol::DireccionGeneral)->create())
            ->get(route('reportes.index'))
            ->assertOk();
    }

    private function administrador(): User
    {
        return User::factory()->conRol(Rol::Administrador)->create();
    }
}
