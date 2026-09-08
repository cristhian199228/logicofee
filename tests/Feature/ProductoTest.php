<?php

namespace Tests\Feature;

use App\Enums\CategoriaProducto;
use App\Enums\Rol;
use App\Models\Lote;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_la_pantalla_lista_el_catalogo_con_su_stock(): void
    {
        Producto::factory()->conStock(40)->create(['nombre' => 'Bourbon Salvador']);
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso']);

        $this->actingAs($this->marketing())
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Bourbon Salvador')
            ->assertSee('Mocha Espresso')
            ->assertViewHas('bajoStock', 1);
    }

    public function test_marketing_agrega_un_cafe_al_catalogo(): void
    {
        $this->actingAs($this->marketing())
            ->post(route('productos.store'), [
                ...$this->datosProducto(),
                'foto' => UploadedFile::fake()->image('geisha.jpg', 800, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $producto = Producto::firstWhere('nombre', 'Geisha Blend Premium');

        $this->assertSame('geisha-blend-premium-500-g', $producto->slug);
        $this->assertSame(CategoriaProducto::Blends, $producto->categoria);
        $this->assertSame('24.90', $producto->precio);
        $this->assertSame(0, $producto->stock);
        $this->assertSame(12, $producto->stock_minimo);
        Storage::disk('public')->assertExists($producto->imagen);
    }

    public function test_dos_cafes_con_el_mismo_nombre_no_comparten_direccion(): void
    {
        foreach (['500 g', '500 g'] as $presentacion) {
            $this->actingAs($this->marketing())
                ->post(route('productos.store'), [...$this->datosProducto(), 'presentacion' => $presentacion])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['geisha-blend-premium-500-g', 'geisha-blend-premium-500-g-2'],
            Producto::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_el_alta_valida_los_datos_del_cafe(): void
    {
        $this->actingAs($this->marketing())
            ->post(route('productos.store'), [
                'nombre' => '',
                'presentacion' => '250 g',
                'categoria' => 'Instantáneo',
                'descripcion' => 'Notas de cacao.',
                'precio' => 0,
                'stock_minimo' => -1,
                'acento' => 'verde',
            ])
            ->assertSessionHasErrors(['nombre', 'categoria', 'precio', 'stock_minimo', 'acento']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_marketing_edita_los_datos_de_un_cafe(): void
    {
        $producto = Producto::factory()->conStock(20)->create([
            'nombre' => 'Bourbon Salvador',
            'precio' => 18.00,
            'stock_minimo' => 10,
        ]);

        $direccion = $producto->slug;

        $this->actingAs($this->marketing())
            ->patch(route('productos.update', $producto), [
                ...$this->datosProducto(),
                'nombre' => 'Bourbon Salvador Reserva',
                'precio' => 21.50,
                'stock_minimo' => 25,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $producto->refresh();

        $this->assertSame('Bourbon Salvador Reserva', $producto->nombre);
        $this->assertSame('21.50', $producto->precio);
        $this->assertSame(25, $producto->stock_minimo);
        // La dirección no cambia: el catálogo y los carritos ya la usan.
        $this->assertSame($direccion, $producto->slug);
        // El stock sigue saliendo de los lotes.
        $this->assertSame(20, $producto->stock);
    }

    public function test_la_foto_nueva_reemplaza_a_la_anterior_al_editar(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->marketing())
            ->patch(route('productos.update', $producto), [
                ...$this->datosProducto(),
                'foto' => UploadedFile::fake()->image('primera.jpg'),
            ]);

        $primera = $producto->fresh()->imagen;

        $this->actingAs($this->marketing())
            ->patch(route('productos.update', $producto), [
                ...$this->datosProducto(),
                'foto' => UploadedFile::fake()->image('segunda.jpg'),
            ]);

        $segunda = $producto->fresh()->imagen;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);
    }

    public function test_la_foto_se_conserva_si_la_edicion_no_envia_una_nueva(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->marketing())
            ->patch(route('productos.update', $producto), [
                ...$this->datosProducto(),
                'foto' => UploadedFile::fake()->image('unica.jpg'),
            ]);

        $imagen = $producto->fresh()->imagen;

        $this->actingAs($this->marketing())
            ->patch(route('productos.update', $producto), $this->datosProducto());

        $this->assertSame($imagen, $producto->fresh()->imagen);
        Storage::disk('public')->assertExists($imagen);
    }

    public function test_se_retira_del_catalogo_un_cafe_sin_stock(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Mocha Espresso']);
        PedidoLinea::factory()->deProducto($producto, 3)->create();

        $this->actingAs($this->marketing())
            ->delete(route('productos.destroy', $producto))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
        // El historial del pedido conserva su copia de los datos.
        $this->assertDatabaseHas('pedido_lineas', ['nombre' => 'Mocha Espresso', 'producto_id' => null]);
    }

    public function test_no_se_retira_un_cafe_que_todavia_tiene_stock(): void
    {
        $producto = Producto::factory()->conStock(15)->create();

        $this->actingAs($this->marketing())
            ->delete(route('productos.destroy', $producto))
            ->assertSessionHasErrors('eliminar', null, 'producto-'.$producto->slug);

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
        $this->assertSame(1, Lote::query()->count());
    }

    public function test_solo_quien_edita_el_catalogo_gestiona_los_productos(): void
    {
        $producto = Producto::factory()->create();

        foreach ([Rol::Administrador, Rol::Proveedor, Rol::MarketingVentas] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('productos.index'))
                ->assertOk();
        }

        foreach ([Rol::Cliente, Rol::LogisticaAlmacen, Rol::ProduccionOperaciones, Rol::DireccionGeneral] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('productos.index'))->assertForbidden();
            $this->actingAs($usuario)->post(route('productos.store'), $this->datosProducto())->assertForbidden();
            $this->actingAs($usuario)->patch(route('productos.update', $producto), $this->datosProducto())->assertForbidden();
            $this->actingAs($usuario)->delete(route('productos.destroy', $producto))->assertForbidden();
        }

        $this->assertDatabaseCount('productos', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosProducto(): array
    {
        return [
            'nombre' => 'Geisha Blend Premium',
            'presentacion' => '500 g',
            'categoria' => CategoriaProducto::Blends->value,
            'descripcion' => 'Notas florales con final a panela.',
            'precio' => 24.90,
            'stock_minimo' => 12,
            'acento' => '#4a7c3f',
        ];
    }

    private function marketing(): User
    {
        return User::factory()->conRol(Rol::MarketingVentas)->create();
    }
}
