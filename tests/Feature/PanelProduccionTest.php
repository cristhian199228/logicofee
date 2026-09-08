<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_plan_sugiere_ordenes_de_tueste_para_lo_que_esta_en_el_minimo(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso', 'stock_minimo' => 25]);
        Producto::factory()->conStock(200)->create(['nombre' => 'Bourbon Salvador', 'stock_minimo' => 10]);

        $this->actingAs($this->produccion())
            ->get(route('produccion.index'))
            ->assertOk()
            ->assertSee('Mocha Espresso')
            ->assertSee('Agotado')
            ->assertViewHas('unidadesSugeridas', 50)
            ->assertViewHas('ordenes', fn ($ordenes) => $ordenes->count() === 1
                && $ordenes->first()['sugerido'] === 50
                && $ordenes->first()['urgente'] === true);
    }

    public function test_el_plan_lista_los_lotes_que_esperan_control_de_calidad(): void
    {
        Lote::factory()->conCantidad(30)->create(['codigo' => 'L-7001']);
        Lote::factory()->conCantidad(20)->aprobado()->create(['codigo' => 'L-7002']);
        Lote::factory()->agotado()->create(['codigo' => 'L-7003']);

        $this->actingAs($this->produccion())
            ->get(route('produccion.index'))
            ->assertOk()
            ->assertSee('L-7001')
            ->assertViewHas('pendientes', fn ($pendientes) => $pendientes->pluck('codigo')->all() === ['L-7001']);
    }

    public function test_el_plan_calcula_el_rendimiento_y_la_merma(): void
    {
        Lote::factory()->conCantidad(100)->aprobado()->create();
        Lote::factory()->conCantidad(100)->aprobado()->create();
        Lote::factory()->conCantidad(100)->rechazado()->create();
        Lote::factory()->conCantidad(100)->conMerma(20)->create();

        $this->actingAs($this->produccion())
            ->get(route('produccion.index'))
            ->assertOk()
            ->assertViewHas('rendimiento', fn (array $rendimiento) => $rendimiento['aprobados'] === 2
                && $rendimiento['rechazados'] === 1
                && $rendimiento['pendientes'] === 1
                && $rendimiento['porcentaje'] === 67)
            ->assertViewHas('unidadesBloqueadas', 100)
            ->assertViewHas('porcentajeDeMerma', 5.0);
    }

    public function test_el_plan_cuenta_las_unidades_tostadas_recientes(): void
    {
        Lote::factory()->conCantidad(80)->create(['tostado_at' => now()->subDays(5)]);
        Lote::factory()->conCantidad(50)->create(['tostado_at' => now()->subMonths(4)]);

        $this->actingAs($this->produccion())
            ->get(route('produccion.index'))
            ->assertOk()
            ->assertViewHas('unidadesProducidas', 80);
    }

    public function test_direccion_ve_el_plan_sin_los_accesos_de_operacion(): void
    {
        Lote::factory()->conCantidad(10)->create();

        $this->actingAs($this->produccion())
            ->get(route('produccion.index'))
            ->assertSee('Registrar lote tostado')
            ->assertSee('Ir al control de calidad');

        $this->actingAs(User::factory()->conRol(Rol::DireccionGeneral)->create())
            ->get(route('produccion.index'))
            ->assertOk()
            ->assertDontSee('Registrar lote tostado')
            ->assertDontSee('Ir al control de calidad');
    }

    public function test_solo_las_areas_de_produccion_abren_el_plan(): void
    {
        foreach ([Rol::Administrador, Rol::DireccionGeneral, Rol::ProduccionOperaciones] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('produccion.index'))
                ->assertOk();
        }

        foreach ([Rol::MarketingVentas, Rol::LogisticaAlmacen, Rol::Proveedor, Rol::Cliente] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('produccion.index'))
                ->assertForbidden();
        }
    }

    private function produccion(): User
    {
        return User::factory()->conRol(Rol::ProduccionOperaciones)->create();
    }
}
