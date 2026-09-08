<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\Seccion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::actingAs($logistica)
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertHasNoErrors();

        Livewire::actingAs($logistica)
            ->test('calidad-tarjeta', ['lote' => $lote])
            ->call('evaluar', 'Aprobado')
            ->assertForbidden();
    }

    public function test_produccion_controla_la_calidad_pero_no_despacha_pedidos(): void
    {
        $pedido = Pedido::factory()->create();
        $lote = Lote::factory()->conCantidad(10)->create();
        $produccion = User::factory()->conRol(Rol::ProduccionOperaciones)->create();

        Livewire::actingAs($produccion)
            ->test('calidad-tarjeta', ['lote' => $lote])
            ->call('evaluar', 'Aprobado')
            ->assertHasNoErrors();

        Livewire::actingAs($produccion)
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertForbidden();
    }

    public function test_marketing_edita_el_catalogo_pero_no_mueve_el_almacen(): void
    {
        $producto = Producto::factory()->create();
        $marketing = User::factory()->conRol(Rol::MarketingVentas)->create();

        Livewire::actingAs($marketing)
            ->test('promocion-editor', ['producto' => $producto])
            ->set('destacado', true)
            ->set('descuento', 15)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($producto->fresh()->destacado);

        Livewire::actingAs($marketing)
            ->test('lotes')
            ->set('producto', $producto->slug)
            ->set('codigo', 'L-3001')
            ->set('cantidad', 10)
            ->call('registrar')
            ->assertForbidden();

        $this->assertDatabaseCount('lotes', 0);
    }

    public function test_direccion_general_solo_consulta(): void
    {
        $direccion = User::factory()->conRol(Rol::DireccionGeneral)->create();

        $this->actingAs($direccion)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($direccion)->get(route('promociones.index'))->assertForbidden();
        $this->actingAs($direccion)->get(route('reportes.index'))->assertOk();
    }

    public function test_direccion_general_no_despacha_ni_controla_la_calidad(): void
    {
        $pedido = Pedido::factory()->create();
        $direccion = User::factory()->conRol(Rol::DireccionGeneral)->create();

        Livewire::actingAs($direccion)
            ->test('seguimiento-tarjeta', ['pedido' => $pedido])
            ->call('avanzar')
            ->assertForbidden();
    }

    public function test_direccion_general_no_registra_controles_de_calidad(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();
        $direccion = User::factory()->conRol(Rol::DireccionGeneral)->create();

        Livewire::actingAs($direccion)
            ->test('calidad-tarjeta', ['lote' => $lote])
            ->call('evaluar', 'Aprobado')
            ->assertForbidden();
    }
}
