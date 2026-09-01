<?php

namespace Tests\Feature;

use App\Enums\CategoriaProducto;
use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_el_catalogo_filtra_por_categoria(): void
    {
        $grano = Producto::factory()->create([
            'nombre' => 'Bourbon Salvador',
            'categoria' => CategoriaProducto::EnGrano,
        ]);
        $blend = Producto::factory()->create([
            'nombre' => 'Geisha Premium',
            'categoria' => CategoriaProducto::Blends,
        ]);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index', ['categoria' => CategoriaProducto::EnGrano->value]))
            ->assertOk()
            ->assertSee($grano->nombre)
            ->assertDontSee($blend->nombre);
    }

    public function test_el_catalogo_busca_por_nombre_y_descripcion(): void
    {
        $buscado = Producto::factory()->create(['nombre' => 'Descafeinado de Altura']);
        $otro = Producto::factory()->create(['nombre' => 'Mocha Espresso', 'descripcion' => 'Tueste oscuro.']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index', ['q' => 'descafeinado']))
            ->assertOk()
            ->assertSee($buscado->nombre)
            ->assertDontSee($otro->nombre);
    }

    public function test_un_producto_agotado_no_ofrece_el_boton_de_agregar(): void
    {
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Sin stock');
    }

    public function test_los_tres_roles_entran_al_catalogo(): void
    {
        Producto::factory()->conStock(10)->create(['nombre' => 'Bourbon Salvador']);

        foreach (Rol::cases() as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('catalogo.index'))
                ->assertOk()
                ->assertSee('Bourbon Salvador');
        }
    }

    public function test_solo_administrador_y_proveedor_ven_el_control_de_fotos(): void
    {
        Producto::factory()->conStock(10)->create();

        $this->actingAs(User::factory()->conRol(Rol::Proveedor)->create())
            ->get(route('catalogo.index'))
            ->assertSee('Foto del producto');

        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->get(route('catalogo.index'))
            ->assertDontSee('Foto del producto');
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }
}
