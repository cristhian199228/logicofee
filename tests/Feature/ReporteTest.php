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
use Livewire\Livewire;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_resume_los_pedidos_por_estado(): void
    {
        Pedido::factory()->count(2)->enEstado(EstadoPedido::Pendiente)->create();
        Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();

        $resumen = Livewire::actingAs($this->administrador())
            ->test('reportes')
            ->assertSee('Pedidos por estado')
            ->assertSee('Ticket promedio')
            ->instance()
            ->resumen;

        $this->assertSame(3, $resumen->totalPedidos());

        $porEstado = $resumen->pedidosPorEstado();

        $this->assertSame(2, $porEstado->firstWhere('estado', EstadoPedido::Pendiente)['cantidad']);
        $this->assertSame(1, $porEstado->firstWhere('estado', EstadoPedido::Entregado)['cantidad']);
    }

    public function test_el_panel_lista_los_productos_mas_vendidos(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();
        $estrella = Producto::factory()->create(['nombre' => 'Geisha Blend Premium']);
        $otro = Producto::factory()->create(['nombre' => 'Mocha Espresso']);

        PedidoLinea::factory()->for($pedido)->deProducto($estrella, 12)->create();
        PedidoLinea::factory()->for($pedido)->deProducto($otro, 3)->create();

        $resumen = Livewire::actingAs($this->administrador())
            ->test('reportes')
            ->assertSee('Geisha Blend Premium')
            ->instance()
            ->resumen;

        $masVendidos = $resumen->productosMasVendidos();

        $this->assertSame('Geisha Blend Premium', $masVendidos->first()['nombre']);
        $this->assertSame(12, $masVendidos->first()['unidades']);
        $this->assertSame(15, $resumen->unidadesVendidas());
    }

    public function test_las_ventas_se_agrupan_por_el_periodo_elegido(): void
    {
        Pedido::factory()->create(['total' => 100.00, 'created_at' => now()]);
        Pedido::factory()->create(['total' => 50.00, 'created_at' => now()->subMonths(2)]);

        $panel = Livewire::actingAs($this->administrador())
            ->test('reportes')
            ->set('periodo', PeriodoReporte::Mes->value);

        $this->assertSame(PeriodoReporte::Mes, $panel->instance()->periodoElegido);

        $ventas = $panel->instance()->resumen->ventasPorTramo();

        $this->assertCount(PeriodoReporte::Mes->tramos(), $ventas);
        $this->assertSame(100.00, $ventas->last()['total']);
        $this->assertSame(150.00, $ventas->sum('total'));

        // El periodo por día solo alcanza los últimos siete tramos.
        $porDia = Livewire::actingAs($this->administrador())->test('reportes');

        $this->assertSame(PeriodoReporte::Dia, $porDia->instance()->periodoElegido);
        $this->assertSame(100.00, $porDia->instance()->resumen->ventasPorTramo()->sum('total'));
    }

    public function test_el_panel_avisa_del_stock_bajo_y_de_los_lotes_bloqueados(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso', 'stock_minimo' => 20]);

        $abastecido = Producto::factory()->create(['nombre' => 'Bourbon Salvador', 'stock_minimo' => 0]);
        Lote::factory()->for($abastecido)->conCantidad(30)->rechazado()->create();
        Lote::factory()->for($abastecido)->conCantidad(15)->create();
        $abastecido->sincronizarStock();

        $resumen = Livewire::actingAs($this->administrador())
            ->test('reportes')
            ->assertSee('Mocha Espresso')
            ->instance()
            ->resumen;

        $this->assertSame(1, $resumen->lotesBloqueados());
        $this->assertSame(1, $resumen->lotesSinEvaluar());
        $this->assertSame(['Mocha Espresso'], $resumen->alertasDeStock()->pluck('nombre')->all());
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
