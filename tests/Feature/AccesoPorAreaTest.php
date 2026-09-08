<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\Seccion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada área empresarial entra por su propio panel y solo ejecuta las acciones
 * que le corresponden.
 */
class AccesoPorAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_rol_entra_por_la_primera_seccion_de_su_menu(): void
    {
        $inicios = [
            Rol::Administrador->value => 'reportes.index',
            Rol::DireccionGeneral->value => 'reportes.index',
            Rol::MarketingVentas->value => 'ventas.index',
            Rol::LogisticaAlmacen->value => 'almacen.index',
            Rol::ProduccionOperaciones->value => 'produccion.index',
            Rol::Proveedor->value => 'seguimiento.index',
            Rol::Cliente->value => 'catalogo.index',
        ];

        foreach (Rol::cases() as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('home'))
                ->assertRedirect(route($inicios[$rol->value]));
        }
    }

    public function test_el_menu_solo_muestra_las_secciones_del_area(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::LogisticaAlmacen)->create())
            ->get(route('almacen.index'))
            ->assertOk()
            ->assertSee(Seccion::Almacen->titulo())
            ->assertSee(Seccion::Lotes->titulo())
            ->assertDontSee(Seccion::Promociones->titulo())
            ->assertDontSee(Seccion::Usuarios->titulo());
    }

    public function test_el_administrador_ve_todas_las_secciones(): void
    {
        $respuesta = $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->get(route('reportes.index'))
            ->assertOk();

        foreach (Seccion::cases() as $seccion) {
            $respuesta->assertSee($seccion->titulo());
        }
    }

    public function test_logistica_despacha_pedidos_pero_no_controla_la_calidad(): void
    {
        $pedido = Pedido::factory()->create();
        $lote = Lote::factory()->conCantidad(10)->create();
        $logistica = User::factory()->conRol(Rol::LogisticaAlmacen)->create();

        $this->actingAs($logistica)
            ->post(route('pedidos.avance.store', $pedido))
            ->assertRedirect();

        $this->actingAs($logistica)
            ->post(route('calidad.store', $lote), ['resultado' => 'Aprobado'])
            ->assertForbidden();
    }

    public function test_produccion_controla_la_calidad_pero_no_despacha_pedidos(): void
    {
        $pedido = Pedido::factory()->create();
        $lote = Lote::factory()->conCantidad(10)->create();
        $produccion = User::factory()->conRol(Rol::ProduccionOperaciones)->create();

        $this->actingAs($produccion)
            ->post(route('calidad.store', $lote), ['resultado' => 'Aprobado'])
            ->assertRedirect();

        $this->actingAs($produccion)
            ->post(route('pedidos.avance.store', $pedido))
            ->assertForbidden();
    }

    public function test_marketing_edita_el_catalogo_pero_no_mueve_el_almacen(): void
    {
        $producto = Producto::factory()->create();
        $marketing = User::factory()->conRol(Rol::MarketingVentas)->create();

        $this->actingAs($marketing)
            ->post(route('lotes.store'), [
                'producto' => $producto->slug,
                'codigo' => 'L-3001',
                'cantidad' => 10,
                'tostado_at' => now()->subWeek()->toDateString(),
                'vence_at' => now()->addYear()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lotes', 0);

        $this->actingAs($marketing)
            ->patch(route('promociones.update', $producto), ['destacado' => '1', 'descuento' => 15])
            ->assertRedirect();
    }

    public function test_direccion_general_solo_consulta(): void
    {
        $pedido = Pedido::factory()->create();
        $lote = Lote::factory()->conCantidad(10)->create();
        $direccion = User::factory()->conRol(Rol::DireccionGeneral)->create();

        $this->actingAs($direccion)->post(route('pedidos.avance.store', $pedido))->assertForbidden();
        $this->actingAs($direccion)->post(route('calidad.store', $lote), ['resultado' => 'Aprobado'])->assertForbidden();
        $this->actingAs($direccion)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($direccion)->get(route('promociones.index'))->assertForbidden();

        $this->actingAs($direccion)->get(route('reportes.index'))->assertOk();
    }
}
