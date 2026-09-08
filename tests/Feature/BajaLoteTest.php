<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BajaLoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistica_retira_del_stock_las_unidades_mermadas(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 0]);
        $lote = Lote::factory()->for($producto)->conCantidad(40)->vencido()->create(['codigo' => 'L-5501']);
        $producto->sincronizarStock();

        $this->actingAs($this->logistica())
            ->post(route('lotes.baja.store', $lote), [
                'cantidad' => 15,
                'motivo' => 'Lote vencido retirado del almacén',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lote->refresh();

        $this->assertSame(25, $lote->cantidad_disponible);
        $this->assertSame(15, $lote->cantidad_baja);
        $this->assertSame('Lote vencido retirado del almacén', $lote->baja_nota);
        $this->assertNotNull($lote->dado_de_baja_at);
        $this->assertSame(25, $producto->fresh()->stock);
    }

    public function test_no_se_dan_de_baja_mas_unidades_de_las_disponibles(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();

        $this->actingAs($this->logistica())
            ->post(route('lotes.baja.store', $lote), ['cantidad' => 11, 'motivo' => 'Humedad'])
            ->assertSessionHasErrors('cantidad', null, 'baja-'.$lote->id);

        $this->assertSame(10, $lote->fresh()->cantidad_disponible);
    }

    public function test_la_baja_exige_un_motivo(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();

        $this->actingAs($this->logistica())
            ->post(route('lotes.baja.store', $lote), ['cantidad' => 2])
            ->assertSessionHasErrors('motivo', null, 'baja-'.$lote->id);

        $this->assertSame(0, $lote->fresh()->cantidad_baja);
    }

    public function test_las_areas_comerciales_no_dan_de_baja_lotes(): void
    {
        $lote = Lote::factory()->conCantidad(10)->create();

        foreach ([Rol::MarketingVentas, Rol::DireccionGeneral, Rol::Cliente] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->post(route('lotes.baja.store', $lote), ['cantidad' => 5, 'motivo' => 'Prueba'])
                ->assertForbidden();
        }

        $this->assertSame(10, $lote->fresh()->cantidad_disponible);
    }

    private function logistica(): User
    {
        return User::factory()->conRol(Rol::LogisticaAlmacen)->create();
    }
}
