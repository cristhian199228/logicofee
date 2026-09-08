<?php

namespace Tests\Feature;

use App\Enums\CategoriaProducto;
use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_cliente_ve_el_catalogo_completo(): void
    {
        $productos = Producto::factory()->count(3)->conStock(25)->create();

        $respuesta = $this->actingAs($this->cliente())->get(route('catalogo.index'));

        $respuesta->assertOk();

        foreach ($productos as $producto) {
            $respuesta->assertSee($producto->nombre);
        }
    }

    public function test_el_catalogo_filtra_por_categoria_sin_recargar(): void
    {
        $grano = Producto::factory()->create([
            'nombre' => 'Bourbon Salvador',
            'categoria' => CategoriaProducto::EnGrano,
        ]);
        $blend = Producto::factory()->create([
            'nombre' => 'Geisha Premium',
            'categoria' => CategoriaProducto::Blends,
        ]);

        $catalogo = Livewire::actingAs($this->cliente())
            ->test('catalogo')
            ->assertSee($grano->nombre)
            ->assertSee($blend->nombre)
            ->call('filtrarPor', CategoriaProducto::EnGrano->value);

        $nombres = $catalogo->instance()->productos->pluck('nombre');

        $this->assertTrue($nombres->contains($grano->nombre));
        $this->assertFalse($nombres->contains($blend->nombre));
    }

    public function test_el_catalogo_busca_por_nombre_y_descripcion_mientras_se_escribe(): void
    {
        $buscado = Producto::factory()->create(['nombre' => 'Descafeinado de Altura']);
        $otro = Producto::factory()->create(['nombre' => 'Mocha Espresso', 'descripcion' => 'Tueste oscuro.']);

        $catalogo = Livewire::actingAs($this->cliente())
            ->test('catalogo')
            ->set('busqueda', 'descafeinado');

        $nombres = $catalogo->instance()->productos->pluck('nombre');

        $this->assertTrue($nombres->contains($buscado->nombre));
        $this->assertFalse($nombres->contains($otro->nombre));
    }

    public function test_la_busqueda_y_la_categoria_viajan_en_la_direccion(): void
    {
        Producto::factory()->create(['nombre' => 'Bourbon Salvador']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index', ['q' => 'bourbon', 'categoria' => CategoriaProducto::EnGrano->value]))
            ->assertOk();

        Livewire::actingAs($this->cliente())
            ->withQueryParams(['q' => 'bourbon'])
            ->test('catalogo')
            ->assertSet('busqueda', 'bourbon');
    }

    public function test_un_producto_agotado_no_ofrece_el_boton_de_agregar(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Sin stock');
    }

    public function test_todos_los_roles_entran_al_catalogo(): void
    {
        Producto::factory()->conStock(10)->create(['nombre' => 'Bourbon Salvador']);

        foreach (Rol::cases() as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('catalogo.index'))
                ->assertOk()
                ->assertSee('Bourbon Salvador');
        }
    }

    public function test_solo_quien_edita_el_catalogo_ve_el_control_de_fotos(): void
    {
        Producto::factory()->conStock(10)->create();

        foreach ([Rol::Proveedor, Rol::MarketingVentas] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('catalogo.index'))
                ->assertSee('Foto del producto');
        }

        foreach ([Rol::Cliente, Rol::LogisticaAlmacen] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('catalogo.index'))
                ->assertDontSee('Foto del producto');
        }
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }
}
