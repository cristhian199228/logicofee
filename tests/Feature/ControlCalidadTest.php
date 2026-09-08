<?php

namespace Tests\Feature;

use App\Enums\ResultadoCalidad;
use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ControlCalidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_lista_los_lotes_por_controlar(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Bourbon Salvador']);
        Lote::factory()->for($producto)->conCantidad(40)->create(['codigo' => 'L-2601']);

        $this->actingAs($this->proveedor())
            ->get(route('calidad.index'))
            ->assertOk()
            ->assertSee('Lotes por controlar')
            ->assertSee('Bourbon Salvador')
            ->assertSee('L-2601');
    }

    public function test_aprobar_un_lote_registra_quien_lo_evaluo(): void
    {
        $lote = Lote::factory()->conCantidad(30)->create();
        $responsable = $this->proveedor();

        $this->actingAs($responsable)
            ->post(route('calidad.store', $lote), [
                'resultado' => ResultadoCalidad::Aprobado->value,
                'calidad_nota' => 'Taza limpia, humedad 11%.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lote->refresh();

        $this->assertSame(ResultadoCalidad::Aprobado, $lote->calidad);
        $this->assertSame($responsable->id, $lote->evaluado_por);
        $this->assertNotNull($lote->evaluado_at);
        $this->assertSame(30, $lote->producto->fresh()->stock);
    }

    public function test_rechazar_un_lote_lo_descuenta_del_stock_del_producto(): void
    {
        $producto = Producto::factory()->create();
        $aprobado = Lote::factory()->for($producto)->conCantidad(20)->aprobado()->create();
        $sospechoso = Lote::factory()->for($producto)->conCantidad(30)->create();
        $producto->sincronizarStock();

        $this->assertSame(50, $producto->fresh()->stock);

        $this->actingAs($this->proveedor())
            ->post(route('calidad.store', $sospechoso), [
                'resultado' => ResultadoCalidad::Rechazado->value,
                'calidad_nota' => 'Humedad fuera de rango.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($sospechoso->fresh()->bloqueado());
        $this->assertSame(20, $producto->fresh()->stock);
        $this->assertSame($aprobado->id, $producto->fresh()->loteActivo()->id);
    }

    public function test_el_pedido_no_consume_un_lote_rechazado(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto)->conCantidad(40)->rechazado()->create();
        $vendible = Lote::factory()->for($producto)->conCantidad(10)->aprobado()->create([
            'vence_at' => now()->addYears(2),
        ]);
        $producto->sincronizarStock();

        $producto->consumirDeLotes(10);

        $this->assertSame(0, $vendible->fresh()->cantidad_disponible);
        $this->assertSame(0, $producto->fresh()->stock);
    }

    public function test_un_lote_rechazado_no_alcanza_para_un_pedido(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto)->conCantidad(40)->rechazado()->create();
        $producto->sincronizarStock();

        $this->expectException(ValidationException::class);

        $producto->consumirDeLotes(5);
    }

    public function test_el_resultado_del_control_debe_ser_valido(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();

        $this->actingAs($this->proveedor())
            ->post(route('calidad.store', $lote), ['resultado' => ResultadoCalidad::Pendiente->value])
            ->assertSessionHasErrors('resultado', null, 'calidad-'.$lote->id);

        $this->assertSame(ResultadoCalidad::Pendiente, $lote->fresh()->calidad);
    }

    public function test_el_cliente_no_accede_al_control_de_calidad(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        $this->actingAs($cliente)->get(route('calidad.index'))->assertForbidden();

        $this->actingAs($cliente)
            ->post(route('calidad.store', $lote), ['resultado' => ResultadoCalidad::Rechazado->value])
            ->assertForbidden();

        $this->assertSame(ResultadoCalidad::Pendiente, $lote->fresh()->calidad);
    }

    private function proveedor(): User
    {
        return User::factory()->conRol(Rol::Proveedor)->create();
    }
}
